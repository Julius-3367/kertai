<?php

namespace App\Domain\Members\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Dues\DueAllocator;
use App\Domain\Dues\Models\Due;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Ledger;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Payouts\Models\Payout;
use App\Domain\Welfare\Models\WelfareCase;
use App\Support\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Leaving the group. One month's notice, then savings are refunded less
 * anything owed. On death the refund goes to the nominee and a welfare case
 * is opened.
 */
final class MemberExit
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly ChartOfAccounts $accounts,
        private readonly DueAllocator $dues,
        private readonly MemberFinance $finance,
        private readonly RequestPayout $payouts,
    ) {}

    public function giveNotice(Member $member, string $reason, ?CarbonImmutable $on = null): Member
    {
        if ($member->status !== 'active') {
            throw new RuleViolation("{$member->name} is {$member->status}.");
        }

        $member->forceFill([
            'status' => 'exiting',
            'exit_notice_on' => ($on ?? CarbonImmutable::today())->toDateString(),
            'exit_reason' => $reason,
        ])->save();

        Audit::log('member.exit_notice', "{$member->name} gave notice to leave: {$reason}", $member);

        return $member;
    }

    public function withdrawNotice(Member $member): Member
    {
        if ($member->status !== 'exiting') {
            throw new RuleViolation('There is no exit notice to withdraw.');
        }

        $member->forceFill(['status' => 'active', 'exit_notice_on' => null, 'exit_reason' => null])->save();
        Audit::log('member.exit_withdrawn', "{$member->name} withdrew their exit notice", $member);

        return $member;
    }

    public function noticeEndsOn(Member $member): ?CarbonImmutable
    {
        $days = (int) Group::findOrFail($member->group_id)->settings()->get('exit_notice_days');

        return $member->exit_notice_on?->addDays($days);
    }

    /**
     * Settle debts from savings and raise the refund payout.
     */
    public function process(Member $member, bool $ignoreNoticePeriod = false): ?Payout
    {
        if (! in_array($member->status, ['exiting', 'deceased'], true)) {
            throw new RuleViolation('Record an exit notice or a death first.');
        }

        if ($member->status === 'exiting' && ! $ignoreNoticePeriod && $this->noticeEndsOn($member)?->isFuture()) {
            throw new RuleViolation('The notice period ends on '.$this->noticeEndsOn($member)->format('j M Y').'.');
        }

        if (Payout::query()->where('type', 'exit_refund')->where('payee_member_id', $member->id)->whereIn('status', ['pending', 'approved', 'paid'])->exists()) {
            throw new RuleViolation('An exit refund has already been raised for this member.');
        }

        return DB::transaction(function () use ($member) {
            $this->dues->payFromSavings($member, 'Exit');

            // Nothing more is owed once the member has left.
            Due::query()->where('member_id', $member->id)->where('type', 'contribution')->open()
                ->whereDate('due_on', '>', CarbonImmutable::today()->toDateString())
                ->update(['status' => 'waived', 'waived_reason' => 'Member left']);

            $savingsAccount = $this->accounts->get($member->group_id, ChartOfAccounts::SAVINGS);
            $refund = $this->ledger->balance($savingsAccount, $member->id);
            $deceased = $member->status === 'deceased';

            if ($refund <= 0) {
                $member->forceFill(['status' => $deceased ? 'deceased' : 'exited', 'exited_on' => now()->toDateString()])->save();

                return null;
            }

            return $this->payouts->handle(
                Group::findOrFail($member->group_id),
                'exit_refund',
                $refund,
                $savingsAccount,
                $deceased ? "Savings of the late {$member->name} to nominee" : "Exit refund for {$member->name}",
                $member,
                $deceased ? ($member->nominee_name ?: "Estate of {$member->name}") : $member->name,
                $deceased ? $member->nominee_phone : $member->phone,
                payable: $member,
                raisedByRules: true,
            );
        });
    }

    public function recordDeath(Member $member, CarbonImmutable $diedOn): WelfareCase
    {
        if ($member->status === 'deceased') {
            throw new RuleViolation('This member is already recorded as deceased.');
        }

        return DB::transaction(function () use ($member, $diedOn) {
            $member->forceFill(['status' => 'deceased', 'exit_reason' => 'Deceased', 'exited_on' => $diedOn->toDateString()])->save();
            $member->terms()->whereNull('ends_on')->update(['ends_on' => $diedOn->toDateString(), 'ended_reason' => 'Deceased']);
            $member->user?->forceFill(['password' => bin2hex(random_bytes(16))])->save();

            $case = WelfareCase::withoutGlobalScopes()->create([
                'group_id' => $member->group_id,
                'member_id' => $member->id,
                'beneficiary_name' => $member->nominee_name ?: "Family of {$member->name}",
                'relationship' => 'self',
                'event' => 'death',
                'description' => "Death of member {$member->name}",
                'event_on' => $diedOn->toDateString(),
                'benefit_amount' => Group::findOrFail($member->group_id)->settings()->welfareBenefit('death', 'self'),
                'opened_by' => auth()->id(),
            ]);

            Audit::log('member.deceased', "Recorded the death of {$member->name}", $member);

            return $case;
        });
    }
}
