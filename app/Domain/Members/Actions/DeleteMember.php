<?php

namespace App\Domain\Members\Actions;

use App\Domain\Access\Models\PositionTerm;
use App\Domain\Access\Permission;
use App\Domain\Audit\Audit;
use App\Domain\Members\Models\Member;
use App\Models\User;
use App\Support\RuleViolation;
use Illuminate\Support\Facades\DB;

/**
 * Removes a member added by mistake or for testing. Anyone with money or
 * programme history stays on the register and leaves through exit instead,
 * so the books never lose a record.
 */
final class DeleteMember
{
    /** Table => what it means to the treasurer, for anything that blocks deletion. */
    private const HISTORY = [
        'payments' => 'payments',
        'journal_lines' => 'ledger entries',
        'loans' => 'loans',
        'loan_guarantors' => 'loan guarantees',
        'welfare_cases' => 'welfare cases',
        'mgr_slots' => 'merry-go-round turns',
        'fundraiser_pledges' => 'fundraiser pledges',
        'project_participants' => 'projects',
        'project_entries' => 'project records',
        'sales' => 'project sales',
        'hires' => 'asset hires',
    ];

    /**
     * @return list<string> what stops this member being deleted
     */
    public function blockers(Member $member): array
    {
        $found = [];

        foreach (self::HISTORY as $table => $label) {
            if (DB::table($table)->where('member_id', $member->id)->exists()) {
                $found[] = $label;
            }
        }

        if (DB::table('payouts')->where('payee_member_id', $member->id)->exists()) {
            $found[] = 'payouts';
        }

        if (DB::table('dues')->where('member_id', $member->id)->where('paid', '>', 0)->exists()) {
            $found[] = 'paid dues';
        }

        return $found;
    }

    public function handle(Member $member, User $by): void
    {
        if ($member->user_id === $by->id) {
            throw new RuleViolation('You cannot delete your own record.');
        }

        $blockers = $this->blockers($member);

        if ($blockers !== []) {
            throw new RuleViolation("{$member->name} has ".implode(', ', $blockers).', so the record must stay. Use "Leaving the group" instead.');
        }

        $this->guardPositionManagers($member);

        $number = $member->member_number;
        $name = $member->name;

        DB::transaction(function () use ($member) {
            $user = $member->user;
            $member->forceFill(['user_id' => null])->save();
            $member->delete();
            $user?->delete();
        });

        Audit::log('member.deleted', "Deleted member {$number} {$name}", null, ['member_number' => $number, 'name' => $name]);
    }

    private function guardPositionManagers(Member $member): void
    {
        $manage = Permission::PositionsManage->value;
        $managers = PositionTerm::query()->current()->with('position')->get()
            ->filter(fn (PositionTerm $t) => in_array($manage, $t->position->permissions ?? [], true));

        if ($managers->contains('member_id', $member->id) && $managers->where('member_id', '!=', $member->id)->isEmpty()) {
            throw new RuleViolation("{$member->name} is the only one in office who can manage positions. Appoint someone else first.");
        }
    }
}
