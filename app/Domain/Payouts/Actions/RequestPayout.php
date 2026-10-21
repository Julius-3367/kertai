<?php

namespace App\Domain\Payouts\Actions;

use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Sequence;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Notifier;
use App\Domain\Payouts\Models\Payout;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RuleViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class RequestPayout
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Notifier $notifier,
    ) {}

    public function handle(
        Group $group,
        string $type,
        int $amount,
        Account $from,
        string $description,
        ?Member $payee = null,
        ?string $payeeName = null,
        ?string $payeePhone = null,
        ?Model $payable = null,
        ?string $batch = null,
        bool $notifyApprovers = true,
        bool $raisedByRules = false,
    ): Payout {
        if ($amount <= 0) {
            throw new RuleViolation('Amount must be more than zero.');
        }

        if ($from->kind !== 'fund' || $from->group_id !== $group->id) {
            throw new RuleViolation('Payouts must come from one of the group funds.');
        }

        if ($from->isPerMember() && $payee === null) {
            throw new RuleViolation('Paying out of member savings needs the member as payee.');
        }

        $this->ensureFunds($from, $amount, $payee);

        $payout = DB::transaction(fn () => Payout::withoutGlobalScopes()->create([
            'group_id' => $group->id,
            'number' => Sequence::next($group->id, 'payouts'),
            'type' => $type,
            'amount' => $amount,
            'account_id' => $from->id,
            'payee_member_id' => $payee?->id,
            'payee_name' => $payeeName ?: ($payee?->name ?? throw new RuleViolation('Enter who is being paid.')),
            'payee_phone' => Phone::normalize($payeePhone) ?? ($payee ? Phone::normalize($payee->phone) : null),
            'description' => $description,
            'payable_type' => $payable?->getMorphClass(),
            'payable_id' => $payable?->getKey(),
            'batch' => $batch,
            // Payouts the rules produce (a merry-go-round turn, a share-out,
            // an exit refund) have no requester, so any two officials other
            // than the payee can approve them. Otherwise an official's own
            // turn, raised by the treasurer, could never reach two approvals.
            'requested_by' => $raisedByRules ? null : auth()->id(),
        ]));

        Audit::log('payout.requested', "Payout #{$payout->number} of ".Money::format($amount)." to {$payout->payee_name} ".($raisedByRules ? 'raised under the group rules' : 'requested'), $payout, [], $group->id);

        if ($notifyApprovers) {
            $this->notifier->toHoldersOf($group, Permission::PayoutsApprove->value, 'payout_approval_needed', [
                'number' => $payout->number,
                'amount' => Money::format($amount),
                'payee' => $payout->payee_name,
                'description' => $description,
                'link' => url("/payouts/{$payout->id}"),
            ], $raisedByRules ? null : auth()->id());
        }

        return $payout;
    }

    public function ensureFunds(Account $from, int $amount, ?Member $payee = null): void
    {
        $available = $this->ledger->balance($from, $from->isPerMember() ? $payee?->id : null);

        if ($available < $amount) {
            throw new RuleViolation("{$from->name} has only ".Money::format($available).' available.');
        }
    }
}
