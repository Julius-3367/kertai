<?php

namespace App\Domain\Loans;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Sequence;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Loans\Models\LoanTransaction;
use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payouts\Models\Payout;
use App\Models\User;
use App\Support\Money;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Everything that moves a loan's balance. Each change writes a line in the
 * loan's own book (principal and interest kept apart) and, where money or
 * value moves, a balanced ledger entry against "Loans to members".
 *
 * Interest earned goes to the group fund, so it is shared out with the rest
 * of the group's income.
 */
final class LoanBook
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ChartOfAccounts $accounts,
    ) {}

    /**
     * @return array{principal: int, interest: int, total: int}
     */
    public function balance(Loan $loan): array
    {
        return $this->balances([$loan->id])[$loan->id];
    }

    /**
     * @param  array<int, int>  $loanIds
     * @return array<int, array{principal: int, interest: int, total: int}>
     */
    public function balances(array $loanIds): array
    {
        $sums = LoanTransaction::query()
            ->whereIn('loan_id', $loanIds)
            ->groupBy('loan_id')
            ->selectRaw('loan_id, SUM(principal) as principal, SUM(interest) as interest')
            ->get()
            ->keyBy('loan_id');

        $out = [];

        foreach ($loanIds as $id) {
            $principal = (int) ($sums[$id]->principal ?? 0);
            $interest = (int) ($sums[$id]->interest ?? 0);
            $out[$id] = ['principal' => $principal, 'interest' => $interest, 'total' => $principal + $interest];
        }

        return $out;
    }

    /**
     * How much the group can lend now: what members have saved plus the
     * group fund, less what is already out on loan, and never more than the
     * cash actually in the bank, M-Pesa and cash box.
     */
    public function lendable(Group|int $group): int
    {
        $balance = fn (string $code) => $this->ledger->balance($this->accounts->get($group, $code));

        $pool = $balance(ChartOfAccounts::SAVINGS) + $balance(ChartOfAccounts::GROUP_FUND) - $balance(ChartOfAccounts::LOANS);
        $cash = $balance(ChartOfAccounts::BANK) + $balance(ChartOfAccounts::MPESA) + $balance(ChartOfAccounts::CASH);

        return max(0, min($pool, $cash));
    }

    /**
     * A loan given before the group used Kertai. `$countedIn` is where the
     * principal is in the books today: usually the group fund (it was never
     * recorded), or the bank if an opening balance already included it.
     *
     * @param  array{principal: int, interest: int, issued_on: CarbonImmutable, due_on?: CarbonImmutable|null, instalment?: int|null, interest_rate: float, interest_method: string, interest_period: string, interest_paused: bool, purpose?: string|null, resolution_reference?: string|null, notes?: string|null}  $data
     */
    public function bringForward(Member $member, array $data, Account $countedIn, ?User $by = null): Loan
    {
        if ($data['principal'] <= 0 && $data['interest'] <= 0) {
            throw new RuleViolation('Enter what the member still owes.');
        }

        if ($countedIn->group_id !== $member->group_id || $countedIn->isPerMember()) {
            throw new RuleViolation('Choose the group fund or the account the money is counted in.');
        }

        $today = CarbonImmutable::today();

        if ($data['issued_on']->gt($today)) {
            throw new RuleViolation('The date the loan was given cannot be in the future.');
        }

        return DB::transaction(function () use ($member, $data, $countedIn, $by, $today) {
            $loan = Loan::withoutGlobalScopes()->create([
                'group_id' => $member->group_id,
                'member_id' => $member->id,
                'number' => Sequence::next($member->group_id, 'loans'),
                'source' => 'brought_forward',
                'status' => 'active',
                'principal' => $data['principal'],
                'purpose' => $data['purpose'] ?? null,
                'instalment' => $data['instalment'] ?? null,
                'interest_rate' => $data['interest_rate'],
                'interest_method' => $data['interest_method'],
                'interest_period' => $data['interest_period'],
                'interest_paused' => $data['interest_paused'],
                'issued_on' => $data['issued_on']->toDateString(),
                'due_on' => ($data['due_on'] ?? null)?->toDateString(),
                'next_interest_on' => $this->nextInterestDate($data['issued_on'], $today)->toDateString(),
                'resolution_reference' => $data['resolution_reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by?->id,
            ]);

            $entry = $this->ledger->post("Loan {$loan->reference()} brought forward for {$member->name}", [
                ['account' => $this->loansAccount($loan), 'debit' => $data['principal'] + $data['interest'], 'member_id' => $member->id, 'memo' => 'Owed when the group moved to Kertai'],
                ['account' => $countedIn, 'credit' => $data['principal'], 'memo' => "Loan {$loan->reference()} principal"],
                ['account' => $this->groupFund($loan), 'credit' => $data['interest'], 'memo' => "Loan {$loan->reference()} interest owed"],
            ], $loan, $today, 'OPENING');

            $this->write($loan, 'opening', $data['principal'], $data['interest'], $today, 'Balance brought forward', journalEntryId: $entry->id, by: $by);

            Audit::log('loan.brought_forward', "Loan {$loan->reference()} for {$member->name} brought forward: ".Money::format($data['principal']).' principal, '.Money::format($data['interest']).' interest', $loan, [
                'resolution' => $data['resolution_reference'] ?? null,
            ]);

            return $loan;
        });
    }

    /**
     * The payout for an approved application has been paid: the loan starts.
     */
    public function disburse(Loan $loan, Payout $payout): void
    {
        $today = CarbonImmutable::today();

        $loan->forceFill([
            'status' => 'active',
            'issued_on' => $today->toDateString(),
            'due_on' => $loan->term_months ? $today->addMonthsNoOverflow($loan->term_months)->toDateString() : null,
            'next_interest_on' => $this->nextInterestDate($today, $today)->toDateString(),
        ])->save();

        $this->write($loan, 'disbursement', $payout->amount, 0, $today, "Paid out by payout #{$payout->number}", journalEntryId: $payout->journal_entry_id);

        if ($loan->interest_period === 'once' && $this->chargesInterest($loan)) {
            $this->chargeInterest($loan, $today, 'once');
        }
    }

    /**
     * Run once a day. On each loan's monthly date, charge a month's interest
     * when interest is on for the group and for that loan. Months while it
     * was off are skipped, never charged later.
     */
    public function accrue(Group $group, CarbonImmutable $today): int
    {
        $charged = 0;

        $loans = Loan::withoutGlobalScopes()
            ->where('group_id', $group->id)
            ->active()
            ->whereNotNull('next_interest_on')
            ->whereDate('next_interest_on', '<=', $today->toDateString())
            ->get();

        foreach ($loans as $loan) {
            $next = $loan->next_interest_on;

            while ($next !== null && $next->lte($today)) {
                if ($loan->interest_period === 'month' && $this->chargesInterest($loan, $group)) {
                    $charged += $this->chargeInterest($loan, $next, $next->format('Y-m')) ? 1 : 0;
                }

                $next = $this->nextInterestDate($loan->issued_on ?? $next, $next);
            }

            $loan->forceFill(['next_interest_on' => $next?->toDateString()])->save();
        }

        return $charged;
    }

    public function chargesInterest(Loan $loan, ?Group $group = null): bool
    {
        $group ??= Group::findOrFail($loan->group_id);

        return $group->settings()->loanInterestOn() && ! $loan->interest_paused && $loan->rate() > 0;
    }

    public function chargeInterest(Loan $loan, CarbonImmutable $on, string $period): ?LoanTransaction
    {
        if (LoanTransaction::query()->where('loan_id', $loan->id)->where('type', 'interest')->where('period', $period)->exists()) {
            return null;
        }

        $base = $loan->interest_method === 'flat' ? $loan->principal : $this->balance($loan)['principal'];
        $amount = (int) round($base * $loan->rate() / 100);

        if ($amount <= 0) {
            return null;
        }

        $label = $period === 'once' ? 'one-off' : $on->format('M Y');
        $entry = $this->ledger->post("Interest on loan {$loan->reference()} for {$loan->member->name} ({$label})", [
            ['account' => $this->loansAccount($loan), 'debit' => $amount, 'member_id' => $loan->member_id, 'memo' => "{$loan->rate()}% interest"],
            ['account' => $this->groupFund($loan), 'credit' => $amount, 'memo' => "Loan {$loan->reference()} interest"],
        ], $loan, $on);

        return $this->write($loan, 'interest', 0, $amount, $on, "Interest {$loan->rate()}% ({$label})", period: $period, journalEntryId: $entry->id);
    }

    /**
     * Apply money received for a loan and return the ledger credit lines for
     * the payment. Anything paid beyond the balance is held as the payer's
     * prepayment, as with contributions.
     *
     * @return list<array{account: Account, credit: int, member_id: int|null, memo: string}>
     */
    public function repay(Loan $loan, int $amount, Payment $payment, ?Member $payer = null): array
    {
        $loan = Loan::withoutGlobalScopes()->with('member')->lockForUpdate()->findOrFail($loan->id);
        $owed = $this->balance($loan);
        $apply = $loan->status === 'active' ? min($amount, max(0, $owed['total'])) : 0;
        $lines = [];

        if ($apply > 0) {
            [$principal, $interest] = $this->split($loan, $apply, $owed);
            $this->write($loan, 'repayment', -$principal, -$interest, CarbonImmutable::parse($payment->received_at ?? now()), 'Repayment'.($payment->receipt_number ? " {$payment->receipt_number}" : ''), paymentId: $payment->id);
            $lines[] = ['account' => $this->loansAccount($loan), 'credit' => $apply, 'member_id' => $loan->member_id, 'memo' => "Loan {$loan->reference()} repayment"];
            $this->closeIfPaid($loan);
        }

        if ($amount > $apply) {
            $lines[] = [
                'account' => $this->accounts->get($loan->group_id, ChartOfAccounts::PREPAYMENTS),
                'credit' => $amount - $apply,
                'member_id' => $payer->id ?? $loan->member_id,
                'memo' => 'Paid in advance',
            ];
        }

        return $lines;
    }

    /**
     * Undo the loan side of a repayment whose payment is being reversed.
     */
    public function reverseRepayment(Payment $payment, string $reason): void
    {
        $line = LoanTransaction::query()->where('payment_id', $payment->id)->where('type', 'repayment')->first();

        if (! $line || LoanTransaction::query()->where('payment_id', $payment->id)->where('type', 'reversal')->exists()) {
            return;
        }

        $loan = Loan::withoutGlobalScopes()->findOrFail($line->loan_id);
        $this->write($loan, 'reversal', -$line->principal, -$line->interest, CarbonImmutable::today(), mb_substr("Repayment reversed: {$reason}", 0, 255), paymentId: $payment->id);

        if (in_array($loan->status, ['cleared', 'written_off'], true)) {
            $loan->forceFill(['status' => 'active', 'closed_on' => null])->save();
            $loan->guarantors()->where('status', 'released')->update(['status' => 'accepted']);
        }
    }

    /**
     * Take money owed on a loan out of a member's savings: the borrower's
     * own, or a guarantor's up to what they guaranteed.
     */
    public function recover(Loan $loan, Member $from, int $amount, string $reason, ?User $by = null): LoanTransaction
    {
        if ($amount <= 0) {
            throw new RuleViolation('Amount must be more than zero.');
        }

        if ($loan->status !== 'active') {
            throw new RuleViolation("Loan {$loan->reference()} is {$loan->status}.");
        }

        $guarantee = $from->id === $loan->member_id
            ? null
            : LoanGuarantor::query()->where('loan_id', $loan->id)->where('member_id', $from->id)->where('status', 'accepted')->first()
                ?? throw new RuleViolation("{$from->name} is not a guarantor of this loan.");

        $savingsAccount = $this->accounts->get($loan->group_id, ChartOfAccounts::SAVINGS);
        $owed = $this->balance($loan);
        $limits = [
            'still owed on the loan' => $owed['total'],
            "{$from->name}'s savings" => $this->ledger->balance($savingsAccount, $from->id),
        ];

        if ($guarantee) {
            $limits["left of {$from->name}'s guarantee"] = $guarantee->amount - $this->recoveredFrom($loan, $from);
        }

        foreach ($limits as $what => $limit) {
            if ($amount > $limit) {
                throw new RuleViolation('That is more than the '.Money::format(max(0, $limit))." {$what}.");
            }
        }

        return DB::transaction(function () use ($loan, $from, $amount, $reason, $by, $owed, $savingsAccount) {
            $entry = $this->ledger->post("Loan {$loan->reference()} recovered from {$from->name}'s savings", [
                ['account' => $savingsAccount, 'debit' => $amount, 'member_id' => $from->id, 'memo' => "Loan {$loan->reference()}: {$reason}"],
                ['account' => $this->loansAccount($loan), 'credit' => $amount, 'member_id' => $loan->member_id, 'memo' => "Recovered from {$from->name}"],
            ], $loan);

            [$principal, $interest] = $this->split($loan, $amount, $owed);
            $line = $this->write($loan, 'recovery', -$principal, -$interest, CarbonImmutable::today(), mb_substr("From {$from->name}'s savings: {$reason}", 0, 255), memberId: $from->id, journalEntryId: $entry->id, by: $by);
            $this->closeIfPaid($loan);

            Audit::log('loan.recovered', 'Recovered '.Money::format($amount)." on loan {$loan->reference()} from {$from->name}'s savings: {$reason}", $loan);

            return $line;
        });
    }

    /**
     * On exit or at share-out a member's own loans come out of their savings
     * before anything is paid to them. What savings cannot cover stays owed.
     */
    public function recoverFromOwnSavings(Member $member, string $why): int
    {
        $savings = $this->accounts->get($member->group_id, ChartOfAccounts::SAVINGS);
        $recovered = 0;

        foreach (Loan::withoutGlobalScopes()->where('member_id', $member->id)->active()->get() as $loan) {
            $take = min($this->balance($loan)['total'], $this->ledger->balance($savings, $member->id));

            if ($take > 0) {
                $this->recover($loan, $member, $take, $why);
                $recovered += $take;
            }
        }

        return $recovered;
    }

    /**
     * Forgive interest, principal, or both. The group fund carries the loss.
     */
    public function waive(Loan $loan, string $part, ?int $amount, string $reason, User $by): LoanTransaction
    {
        if ($loan->status !== 'active') {
            throw new RuleViolation("Loan {$loan->reference()} is {$loan->status}.");
        }

        $owed = $this->balance($loan);
        [$principal, $interest] = match ($part) {
            'interest' => [0, min($amount ?? $owed['interest'], $owed['interest'])],
            'principal' => [min($amount ?? $owed['principal'], $owed['principal']), 0],
            default => [$owed['principal'], $owed['interest']],
        };

        if ($principal + $interest <= 0) {
            throw new RuleViolation('There is nothing of that kind left to waive.');
        }

        return DB::transaction(function () use ($loan, $principal, $interest, $reason, $by) {
            $entry = $this->ledger->post("Loan {$loan->reference()} for {$loan->member->name}: waived", [
                ['account' => $this->groupFund($loan), 'debit' => $principal + $interest, 'memo' => $reason],
                ['account' => $this->loansAccount($loan), 'credit' => $principal + $interest, 'member_id' => $loan->member_id, 'memo' => 'Waived'],
            ], $loan);

            $line = $this->write($loan, 'waiver', -$principal, -$interest, CarbonImmutable::today(), mb_substr("Waived: {$reason}", 0, 255), journalEntryId: $entry->id, by: $by);
            $this->closeIfPaid($loan);

            Audit::log('loan.waived', 'Waived '.Money::format($principal).' principal and '.Money::format($interest)." interest on loan {$loan->reference()}: {$reason}", $loan);

            return $line;
        });
    }

    /**
     * Change one loan's interest: the rate, how it is worked out, or stop and
     * restart it. Months while interest is stopped are never charged.
     */
    public function setInterest(Loan $loan, float $rate, string $method, string $period, bool $paused, string $reason): Loan
    {
        $before = $loan->only(['interest_rate', 'interest_method', 'interest_period', 'interest_paused']);

        $loan->forceFill([
            'interest_rate' => $rate,
            'interest_method' => $method,
            'interest_period' => $period,
            'interest_paused' => $paused,
            'interest_note' => mb_substr($reason, 0, 255),
        ]);

        if ($loan->next_interest_on?->isPast() && $loan->issued_on) {
            $loan->next_interest_on = $this->nextInterestDate($loan->issued_on, CarbonImmutable::today());
        }

        $loan->save();

        Audit::log('loan.interest_changed', "Interest on loan {$loan->reference()} ".($paused ? 'stopped' : "set to {$rate}% ({$method}, per {$period})").": {$reason}", $loan, [
            'before' => $before,
            'after' => $loan->only(array_keys($before)),
        ]);

        return $loan;
    }

    /**
     * What has been taken from this guarantor's savings for the loan so far.
     */
    public function recoveredFrom(Loan $loan, Member $member): int
    {
        return -(int) LoanTransaction::query()
            ->where('loan_id', $loan->id)
            ->where('type', 'recovery')
            ->where('member_id', $member->id)
            ->selectRaw('SUM(principal + interest) as total')
            ->value('total');
    }

    /**
     * Savings a member has promised as guarantor on other people's running
     * loans, less anything already taken.
     */
    public function guaranteed(Member $member): int
    {
        return (int) LoanGuarantor::query()
            ->with('loan')
            ->where('member_id', $member->id)
            ->where('status', 'accepted')
            ->whereHas('loan', fn ($q) => $q->withoutGlobalScopes()->whereIn('status', Loan::OPEN))
            ->get()
            ->sum(fn (LoanGuarantor $g) => max(0, $g->amount - $this->recoveredFrom($g->loan, $member)));
    }

    /**
     * Instalments not yet paid: what should have come off the principal by
     * now, at the agreed monthly amount, less what actually has.
     */
    public function behind(Loan $loan, ?int $principalOwed = null, ?CarbonImmutable $on = null): int
    {
        if (! $loan->instalment || $loan->status !== 'active') {
            return 0;
        }

        $on ??= CarbonImmutable::today();
        $start = $loan->source === 'brought_forward'
            ? CarbonImmutable::parse($loan->created_at)->startOfDay()
            : $loan->issued_on;

        if ($start === null) {
            return 0;
        }

        $months = (int) floor($start->diffInMonths($on));
        $expected = min($loan->principal, $loan->instalment * max(0, $months));
        $repaid = $loan->principal - ($principalOwed ?? $this->balance($loan)['principal']);

        return max(0, $expected - $repaid);
    }

    /**
     * Split an amount between principal and interest in the order the group
     * rules set.
     *
     * @param  array{principal: int, interest: int, total: int}  $owed
     * @return array{0: int, 1: int}
     */
    private function split(Loan $loan, int $amount, array $owed): array
    {
        $order = (string) Group::findOrFail($loan->group_id)->settings()->get('loan_repayment_order');

        if ($order === 'interest_first') {
            $interest = min($amount, max(0, $owed['interest']));

            return [min($amount - $interest, max(0, $owed['principal'])), $interest];
        }

        $principal = min($amount, max(0, $owed['principal']));

        return [$principal, min($amount - $principal, max(0, $owed['interest']))];
    }

    private function closeIfPaid(Loan $loan): void
    {
        if ($this->balance($loan)['total'] > 0) {
            return;
        }

        $writtenOff = LoanTransaction::query()->where('loan_id', $loan->id)->where('type', 'waiver')->where('principal', '<', 0)->exists();

        $loan->forceFill(['status' => $writtenOff ? 'written_off' : 'cleared', 'closed_on' => CarbonImmutable::today()->toDateString()])->save();
        $loan->guarantors()->where('status', 'accepted')->update(['status' => 'released']);
    }

    /**
     * The first monthly interest date after `$after`, on the day of the month
     * the loan was given (the 28th at the latest, so every month has it).
     */
    private function nextInterestDate(CarbonImmutable $issued, CarbonImmutable $after): CarbonImmutable
    {
        $day = min($issued->day, 28);
        $candidate = $after->startOfMonth()->setDay($day);

        return $candidate->gt($after->startOfDay()) ? $candidate : $candidate->addMonthNoOverflow()->setDay($day);
    }

    private function write(
        Loan $loan,
        string $type,
        int $principal,
        int $interest,
        CarbonImmutable $on,
        string $description,
        ?string $period = null,
        ?int $memberId = null,
        ?int $paymentId = null,
        ?int $journalEntryId = null,
        ?User $by = null,
    ): LoanTransaction {
        return LoanTransaction::create([
            'loan_id' => $loan->id,
            'type' => $type,
            'principal' => $principal,
            'interest' => $interest,
            'occurred_on' => $on->toDateString(),
            'period' => $period,
            'description' => $description,
            'member_id' => $memberId,
            'payment_id' => $paymentId,
            'journal_entry_id' => $journalEntryId,
            'created_by' => $by->id ?? auth()->id(),
        ]);
    }

    private function loansAccount(Loan $loan): Account
    {
        return $this->accounts->get($loan->group_id, ChartOfAccounts::LOANS);
    }

    private function groupFund(Loan $loan): Account
    {
        return $this->accounts->get($loan->group_id, ChartOfAccounts::GROUP_FUND);
    }
}
