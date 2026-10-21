<?php

namespace App\Domain\Mgr;

use App\Domain\Audit\Audit;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Members\MemberFinance;
use App\Domain\Members\Models\Member;
use App\Domain\Mgr\Models\MgrCycle;
use App\Domain\Mgr\Models\MgrSlot;
use App\Domain\Mgr\Models\MgrSwap;
use App\Domain\Notifications\Notifier;
use App\Domain\Payouts\Actions\RequestPayout;
use App\Domain\Payouts\Models\Payout;
use App\Models\User;
use App\Support\Money;
use App\Support\RuleViolation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class MerryGoRound
{
    public function __construct(
        private readonly MemberFinance $finance,
        private readonly RequestPayout $payouts,
        private readonly ChartOfAccounts $accounts,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Draw the order by ballot. The seed is stored so anyone can re-run the
     * draw and get the same order.
     *
     * @param  list<int>  $memberIds
     */
    public function ballot(MgrCycle $cycle, array $memberIds, User $by, ?string $seed = null): MgrCycle
    {
        if ($cycle->balloted_at !== null) {
            throw new RuleViolation('This cycle has already been balloted.');
        }

        $members = Member::query()->whereIn('id', $memberIds)->active()->orderBy('id')->get();

        if ($members->count() < 2) {
            throw new RuleViolation('A merry-go-round needs at least two active members.');
        }

        $seed ??= Str::lower(Str::random(16));
        $order = $this->draw(array_values(array_map('intval', $members->pluck('id')->all())), $seed);
        $group = Group::findOrFail($cycle->group_id);

        DB::transaction(function () use ($cycle, $order, $seed, $by, $group) {
            foreach ($order as $index => $memberId) {
                MgrSlot::create([
                    'mgr_cycle_id' => $cycle->id,
                    'member_id' => $memberId,
                    'position' => $index + 1,
                    'scheduled_on' => $cycle->dateForPosition($index + 1)->toDateString(),
                ]);
            }

            $cycle->forceFill([
                'ballot_seed' => $seed,
                'balloted_at' => now(),
                'balloted_by' => $by->id,
                'status' => 'active',
                'payout_amount' => $cycle->payout_amount ?: count($order) * (int) $group->settings()->get('mgr_share'),
            ])->save();
        });

        Audit::log('mgr.balloted', "Balloted {$cycle->name} for ".count($order)." members (seed {$seed})", $cycle);

        return $cycle->refresh();
    }

    /**
     * @param  list<int>  $memberIds
     * @return list<int>
     */
    public function draw(array $memberIds, string $seed): array
    {
        sort($memberIds);
        $randomizer = new Randomizer(new Mt19937(crc32($seed)));

        return $randomizer->shuffleArray($memberIds);
    }

    /**
     * Raise the payout for the next member in line. Members in arrears are
     * moved to the back (when the group rule says so) and the next member
     * is paid instead.
     *
     * @return array{payout: Payout|null, deferred: list<string>}
     */
    public function releaseNext(MgrCycle $cycle): array
    {
        $deferred = [];
        $skipArrears = (bool) Group::findOrFail($cycle->group_id)->settings()->get('mgr_skip_members_in_arrears');

        if ($cycle->slots()->where('status', 'requested')->exists()) {
            throw new RuleViolation('The current turn is still waiting for its payout to be approved and paid.');
        }

        $upcoming = $cycle->slots()->where('status', 'upcoming')->with('member')->get();
        $attempts = $upcoming->count();

        while ($attempts-- > 0) {
            /** @var MgrSlot|null $slot */
            $slot = $cycle->slots()->where('status', 'upcoming')->with('member')->first();

            if ($slot === null) {
                break;
            }

            $arrears = $this->finance->arrears($slot->member);

            if ($skipArrears && $arrears > 0 && $this->hasEligibleAfter($cycle, $slot)) {
                $this->deferToEnd($cycle, $slot);
                $deferred[] = $slot->member->name;

                continue;
            }

            $payout = DB::transaction(function () use ($cycle, $slot) {
                $payout = $this->payouts->handle(
                    Group::findOrFail($cycle->group_id),
                    'mgr',
                    $cycle->payout_amount,
                    $this->accounts->get($cycle->group_id, ChartOfAccounts::MGR),
                    "{$cycle->name}: turn {$slot->position}",
                    $slot->member,
                    payable: $slot,
                    raisedByRules: true,
                );

                $slot->forceFill(['status' => 'requested', 'payout_id' => $payout->id])->save();

                return $payout;
            });

            $this->notifier->toMember($slot->member, 'mgr_turn', [
                'amount' => Money::format($cycle->payout_amount),
                'date' => $slot->scheduled_on->format('j M Y'),
            ]);

            return ['payout' => $payout, 'deferred' => $deferred];
        }

        if (! $cycle->slots()->whereIn('status', ['upcoming', 'requested'])->exists()) {
            $cycle->forceFill(['status' => 'completed'])->save();
        }

        return ['payout' => null, 'deferred' => $deferred];
    }

    public function requestSwap(MgrSlot $mine, MgrSlot $theirs, ?string $reason): MgrSwap
    {
        if ($mine->mgr_cycle_id !== $theirs->mgr_cycle_id || $mine->id === $theirs->id) {
            throw new RuleViolation('Pick another member in the same cycle.');
        }

        if ($mine->status !== 'upcoming' || $theirs->status !== 'upcoming') {
            throw new RuleViolation('Only turns that have not been paid can be swapped.');
        }

        $open = MgrSwap::query()
            ->whereIn('status', ['pending_member', 'pending_chair'])
            ->where(fn ($q) => $q->whereIn('requester_slot_id', [$mine->id, $theirs->id])->orWhereIn('target_slot_id', [$mine->id, $theirs->id]))
            ->exists();

        if ($open) {
            throw new RuleViolation('One of these turns already has a swap request open.');
        }

        return MgrSwap::create([
            'mgr_cycle_id' => $mine->mgr_cycle_id,
            'requester_slot_id' => $mine->id,
            'target_slot_id' => $theirs->id,
            'reason' => $reason,
        ]);
    }

    public function respondAsMember(MgrSwap $swap, bool $accept): MgrSwap
    {
        if ($swap->status !== 'pending_member') {
            throw new RuleViolation('This swap is no longer waiting for your answer.');
        }

        $swap->forceFill([
            'status' => $accept ? 'pending_chair' : 'declined',
            'member_responded_at' => now(),
        ])->save();

        return $swap;
    }

    public function respondAsChair(MgrSwap $swap, User $chair, bool $approve): MgrSwap
    {
        if ($swap->status !== 'pending_chair') {
            throw new RuleViolation('This swap is not waiting for the chair.');
        }

        DB::transaction(function () use ($swap, $chair, $approve) {
            if ($approve) {
                $a = $swap->requesterSlot()->lockForUpdate()->first();
                $b = $swap->targetSlot()->lockForUpdate()->first();

                if ($a->status !== 'upcoming' || $b->status !== 'upcoming') {
                    throw new RuleViolation('One of these turns has already been paid.');
                }

                [$posA, $dateA] = [$a->position, $a->scheduled_on];
                $a->forceFill(['position' => $b->position, 'scheduled_on' => $b->scheduled_on])->save();
                $b->forceFill(['position' => $posA, 'scheduled_on' => $dateA])->save();
            }

            $swap->forceFill([
                'status' => $approve ? 'approved' : 'declined',
                'chair_responded_at' => now(),
                'chair_user_id' => $chair->id,
            ])->save();
        });

        Audit::log($approve ? 'mgr.swap_approved' : 'mgr.swap_declined', 'Merry-go-round swap '.($approve ? 'approved' : 'declined'), $swap->cycle);

        return $swap->refresh();
    }

    private function hasEligibleAfter(MgrCycle $cycle, MgrSlot $slot): bool
    {
        /** @var Collection<int, MgrSlot> $later */
        $later = $cycle->slots()->where('status', 'upcoming')->where('position', '>', $slot->position)->with('member')->get();

        return $later->contains(fn (MgrSlot $s) => $this->finance->arrears($s->member) === 0);
    }

    private function deferToEnd(MgrCycle $cycle, MgrSlot $slot): void
    {
        DB::transaction(function () use ($cycle, $slot) {
            $last = (int) $cycle->slots()->max('position');

            MgrSlot::query()
                ->where('mgr_cycle_id', $cycle->id)
                ->where('position', '>', $slot->position)
                ->orderBy('position')
                ->get()
                ->each(fn (MgrSlot $s) => $s->forceFill([
                    'position' => $s->position - 1,
                    'scheduled_on' => $s->status === 'upcoming' ? $cycle->dateForPosition($s->position - 1)->toDateString() : $s->scheduled_on,
                ])->save());

            $slot->forceFill([
                'position' => $last,
                'scheduled_on' => $cycle->dateForPosition($last)->toDateString(),
                'deferred_count' => $slot->deferred_count + 1,
            ])->save();
        });

        Audit::log('mgr.deferred', "{$slot->member->name} moved to the end of {$cycle->name} for arrears", $cycle);
    }
}
