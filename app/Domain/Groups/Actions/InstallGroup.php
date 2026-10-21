<?php

namespace App\Domain\Groups\Actions;

use App\Domain\Access\DefaultPositions;
use App\Domain\Access\Models\Position;
use App\Domain\Documents\Models\ComplianceItem;
use App\Domain\Groups\Models\Group;
use App\Domain\Ledger\ChartOfAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a group with its chart of accounts, the standard positions and a
 * starter compliance calendar. Safe to run again: nothing is duplicated.
 */
final class InstallGroup
{
    public function __construct(private readonly ChartOfAccounts $accounts) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $name, array $attributes = []): Group
    {
        return DB::transaction(function () use ($name, $attributes) {
            $group = Group::firstOrCreate(
                ['slug' => Str::slug($attributes['slug'] ?? $name)],
                ['name' => $name, ...$attributes],
            );

            $this->accounts->install($group);
            $this->installPositions($group);
            $this->installCompliance($group);

            return $group;
        });
    }

    public function installPositions(Group $group): void
    {
        foreach (DefaultPositions::all() as $slug => $definition) {
            Position::withoutGlobalScopes()->firstOrCreate(
                ['group_id' => $group->id, 'slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'permissions' => array_map(fn ($p) => $p->value, $definition['permissions']),
                    'is_official' => $definition['is_official'],
                    'is_system' => true,
                    'max_holders' => $definition['max_holders'],
                    'sort' => $definition['sort'],
                ],
            );
        }
    }

    private function installCompliance(Group $group): void
    {
        if (ComplianceItem::withoutGlobalScopes()->where('group_id', $group->id)->exists()) {
            return;
        }

        $year = CarbonImmutable::today()->year;
        $items = [
            ['title' => 'Renew self-help group certificate (Department of Social Development)', 'due_on' => "{$year}-12-31", 'recurrence' => 'yearly', 'position' => 'secretary'],
            ['title' => 'Hold the annual general meeting and elections', 'due_on' => ($year + 1).'-01-31', 'recurrence' => 'yearly', 'position' => 'chairperson'],
            ['title' => 'Audit of the books by the auditor', 'due_on' => ($year + 1).'-01-15', 'recurrence' => 'yearly', 'position' => 'auditor'],
            ['title' => 'December share-out', 'due_on' => "{$year}-12-15", 'recurrence' => 'yearly', 'position' => 'treasurer'],
            ['title' => 'Monthly bank and M-Pesa reconciliation', 'due_on' => CarbonImmutable::today()->endOfMonth()->toDateString(), 'recurrence' => 'monthly', 'position' => 'treasurer'],
        ];

        foreach ($items as $item) {
            ComplianceItem::withoutGlobalScopes()->create([
                'group_id' => $group->id,
                'title' => $item['title'],
                'due_on' => $item['due_on'],
                'recurrence' => $item['recurrence'],
                'position_id' => Position::withoutGlobalScopes()->where('group_id', $group->id)->where('slug', $item['position'])->value('id'),
            ]);
        }
    }
}
