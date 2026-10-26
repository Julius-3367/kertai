<?php

namespace App\Domain\Loans;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Sequence;
use App\Domain\Loans\Models\Loan;
use App\Domain\Loans\Models\LoanGuarantor;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Payouts\Models\Payout;
use App\Models\User;
use App\Support\Money;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * A member asks for a loan and names guarantors; each guarantor accepts in
 * the app; the treasurer sends it on as a payout, which needs the usual two
 * approvals; when the payout is paid the loan starts (see LoanBook::disburse).
 */
final class LoanApplications
{
    public function __construct(
        private readonly LoanEligibility $eligibility,
        private readonly RequestPayout $payouts,
        private readonly ChartOfAccounts $accounts,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<int, int>  $guarantors  member id => amount guaranteed (cents)
     */
    public function apply(Member $member, int $amount, int $termMonths, string $purpose, array $guarantors): Loan
    {
        $rules = $this->eligibility->check($member, $amount, $termMonths);

        if (! LoanEligibility::passes($rules)) {
            $failed = collect($rules)->reject(fn ($r) => $r['ok'])->pluck('label')->implode('; ');

            throw new RuleViolation("This loan cannot be applied for yet: {$failed}.");
        }

        $this->checkGuarantors($member, $amount, $guarantors);
        $group = Group::findOrFail($member->group_id);

        $loan = DB::transaction(function () use ($member, $amount, $termMonths, $purpose, $guarantors, $group) {
            $loan = Loan::withoutGlobalScopes()->create([
                'group_id' => $member->group_id,
                'member_id' => $member->id,
                'number' => Sequence::next($member->group_id, 'loans'),
                'source' => 'application',
                'status' => 'pending',
                'principal' => $amount,
                'purpose' => $purpose,
                'term_months' => $termMonths,
                'instalment' => (int) ceil($amount / $termMonths),
                ...$group->settings()->loanTerms(),
                'created_by' => auth()->id(),
            ]);

            foreach ($guarantors as $memberId => $cents) {
                LoanGuarantor::create(['loan_id' => $loan->id, 'member_id' => $memberId, 'amount' => $cents]);
            }

            return $loan;
        });

        Audit::log('loan.applied', "{$member->name} applied for loan {$loan->reference()} of ".Money::format($amount)." over {$termMonths} months", $loan);

        foreach ($loan->guarantors()->with('member')->get() as $guarantor) {
            $this->notifier->toMember($guarantor->member, 'guarantor_request', [
                'borrower' => $member->name,
                'amount' => Money::format($guarantor->amount),
                'loan_amount' => Money::format($amount),
                'link' => url("/loans/{$loan->id}"),
            ]);
        }

        return $loan;
    }

    public function respond(LoanGuarantor $guarantor, Member $member, bool $accept): LoanGuarantor
    {
        $loan = $guarantor->loan;

        if ($guarantor->member_id !== $member->id) {
            throw new RuleViolation('Only the member asked can answer this.');
        }

        if ($loan->status !== 'pending' || $guarantor->status !== 'pending') {
            throw new RuleViolation('This request has already been answered or the loan has moved on.');
        }

        if ($accept && $this->eligibility->freeSavings($member) < $guarantor->amount) {
            throw new RuleViolation('Your savings not already pledged ('.Money::format($this->eligibility->freeSavings($member)).') do not cover '.Money::format($guarantor->amount).'.');
        }

        $guarantor->forceFill(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now()])->save();
        Audit::log($accept ? 'loan.guarantee_accepted' : 'loan.guarantee_declined', "{$member->name} ".($accept ? 'accepted' : 'declined').' to guarantee '.Money::format($guarantor->amount)." of loan {$loan->reference()}", $loan);

        $this->notifier->toMember($loan->member, 'guarantor_response', [
            'guarantor' => $member->name,
            'answer' => $accept ? 'accepted' : 'declined',
            'loan' => $loan->reference(),
            'link' => url("/loans/{$loan->id}"),
        ]);

        if ($accept && $this->ready($loan)) {
            $this->notifier->toHoldersOf(Group::findOrFail($loan->group_id), Permission::LoansManage->value, 'loan_ready', [
                'borrower' => $loan->member->name,
                'loan' => $loan->reference(),
                'amount' => Money::format($loan->principal),
                'link' => url("/loans/{$loan->id}"),
            ]);
        }

        return $guarantor;
    }

    /** Every guarantor has accepted and together they cover what is needed. */
    public function ready(Loan $loan): bool
    {
        $guarantors = $loan->guarantors()->get();
        $accepted = $guarantors->where('status', 'accepted');

        return $loan->status === 'pending'
            && $guarantors->every(fn (LoanGuarantor $g) => $g->status === 'accepted')
            && $accepted->count() >= $this->eligibility->guarantorsRequired($loan->member)
            && $accepted->sum('amount') >= $this->eligibility->coverNeeded($loan->member, $loan->principal);
    }

    /**
     * Raise the payout that pays the loan out. It goes through the normal
     * two approvals; nothing leaves until it is marked paid.
     */
    public function submit(Loan $loan): Payout
    {
        if ($loan->status !== 'pending') {
            throw new RuleViolation("Loan {$loan->reference()} is {$loan->status}.");
        }

        if (! $this->ready($loan)) {
            throw new RuleViolation('Every guarantor must accept, and together cover the loan, before it goes for approval.');
        }

        $rules = $this->eligibility->check($loan->member, $loan->principal, $loan->term_months, $loan);

        if (! LoanEligibility::passes($rules)) {
            throw new RuleViolation('The member no longer meets the lending rules: '.collect($rules)->reject(fn ($r) => $r['ok'])->pluck('label')->implode('; ').'.');
        }

        $group = Group::findOrFail($loan->group_id);

        return DB::transaction(function () use ($loan, $group) {
            $payout = $this->payouts->handle(
                $group,
                'loan',
                $loan->principal,
                $this->accounts->get($group, ChartOfAccounts::LOANS),
                "Loan {$loan->reference()} ({$loan->term_months} months)",
                $loan->member,
                payable: $loan,
            );

            $loan->forceFill(['status' => 'submitted', 'payout_id' => $payout->id])->save();
            Audit::log('loan.submitted', "Loan {$loan->reference()} sent for approval as payout #{$payout->number}", $loan);

            return $payout;
        });
    }

    public function reject(Loan $loan, string $reason, User $by): Loan
    {
        if ($loan->status !== 'pending') {
            throw new RuleViolation("Loan {$loan->reference()} is {$loan->status}.");
        }

        $this->close($loan, 'rejected');
        Audit::log('loan.rejected', "Loan {$loan->reference()} turned down by {$by->name}: {$reason}", $loan);

        return $loan;
    }

    public function cancel(Loan $loan, Member $member): Loan
    {
        if ($loan->member_id !== $member->id || $loan->status !== 'pending') {
            throw new RuleViolation('Only a pending application can be withdrawn, by the member who made it.');
        }

        $this->close($loan, 'cancelled');
        Audit::log('loan.cancelled', "{$member->name} withdrew loan application {$loan->reference()}", $loan);

        return $loan;
    }

    /**
     * The loan's payout was rejected or cancelled. A rejection ends the
     * application; a cancellation sends it back so it can be raised again.
     */
    public static function payoutClosed(Loan $loan, Payout $payout): void
    {
        if ($loan->status !== 'submitted' || $loan->payout_id !== $payout->id) {
            return;
        }

        if ($payout->status === 'rejected') {
            $loan->forceFill(['status' => 'rejected', 'closed_on' => now()->toDateString()])->save();
            $loan->guarantors()->whereIn('status', ['pending', 'accepted'])->update(['status' => 'released']);
        } else {
            $loan->forceFill(['status' => 'pending', 'payout_id' => null])->save();
        }
    }

    /**
     * @param  array<int, int>  $guarantors
     */
    private function checkGuarantors(Member $borrower, int $amount, array $guarantors): void
    {
        $required = $this->eligibility->guarantorsRequired($borrower);

        if (count($guarantors) < $required) {
            throw new RuleViolation("Name at least {$required} guarantors.");
        }

        $cover = $this->eligibility->coverNeeded($borrower, $amount);

        if (array_sum($guarantors) < $cover) {
            throw new RuleViolation('Your guarantors must together cover '.Money::format($cover).', the part of the loan your own savings do not.');
        }

        foreach ($guarantors as $memberId => $cents) {
            $guarantor = Member::query()->find($memberId);

            if (! $guarantor || ! $guarantor->isActive() || $guarantor->id === $borrower->id) {
                throw new RuleViolation('Guarantors must be other active members.');
            }

            if ($cents <= 0) {
                throw new RuleViolation("Enter how much {$guarantor->name} guarantees.");
            }

            if ($this->eligibility->freeSavings($guarantor) < $cents) {
                // Do not tell the applicant how much someone else has saved.
                throw new RuleViolation("{$guarantor->name}'s savings cannot cover a guarantee of ".Money::format($cents).'. Ask for less, or name someone else.');
            }
        }
    }

    private function close(Loan $loan, string $status): void
    {
        $loan->forceFill(['status' => $status, 'closed_on' => now()->toDateString()])->save();
        $loan->guarantors()->whereIn('status', ['pending', 'accepted'])->update(['status' => 'released']);
    }
}
