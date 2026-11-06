<?php

namespace App\Domain\Projects\Models;

use App\Domain\Access\Permission;
use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Models\Account;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An income-generating activity with its own fund: coffee seedlings,
 * sugarcane, tents and chairs, anything the group invests in.
 *
 * @property int $id
 * @property int $group_id
 * @property string $name
 * @property string $category
 * @property string|null $description
 * @property string $status planning|active|closed
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string $share_basis contribution|equal
 * @property int|null $coordinator_member_id
 * @property int|null $account_id
 */
class Project extends Model
{
    use BelongsToGroup;

    public const CATEGORIES = [
        'crops' => 'Crops and seedlings',
        'livestock' => 'Livestock',
        'hire' => 'Hire services',
        'trading' => 'Trading',
        'other' => 'Other',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'coordinator_member_id');
    }

    /** @return HasMany<ProjectParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ProjectParticipant::class);
    }

    /** @return HasMany<ProjectEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ProjectEntry::class)->latest('entry_on')->latest('id');
    }

    /** @return HasMany<ProjectItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)->latest('sold_on')->latest('id');
    }

    /** @return HasMany<ProjectPlot, $this> */
    public function plots(): HasMany
    {
        return $this->hasMany(ProjectPlot::class);
    }

    /** @return HasMany<ProjectSeason, $this> */
    public function seasons(): HasMany
    {
        return $this->hasMany(ProjectSeason::class)->latest('starts_on')->latest('id');
    }

    /** @return HasMany<ProjectCost, $this> */
    public function costs(): HasMany
    {
        return $this->hasMany(ProjectCost::class)->latest('incurred_on')->latest('id');
    }

    /**
     * Holders of "Manage projects" run every project. The member named as a
     * project's coordinator also keeps its land, seasons and costs.
     */
    public function costsManagedBy(User $user): bool
    {
        return $user->hasPermission(Permission::ProjectsManage->value)
            || ($this->coordinator_member_id !== null && $user->member?->id === $this->coordinator_member_id);
    }

    /** The open season a cost or sale on this date belongs to, if any. */
    public function seasonFor(CarbonImmutable $date): ?ProjectSeason
    {
        return $this->seasons()->where('status', 'open')->get()->first(fn (ProjectSeason $s) => $s->covers($date));
    }

    /** Land in use, in acres. */
    public function acres(): float
    {
        return round($this->plots()->where('status', 'active')->get()->sum(fn (ProjectPlot $p) => $p->acres()), 2);
    }

    public function fundAccount(): Account
    {
        if ($this->account_id === null) {
            $account = app(ChartOfAccounts::class)->openFor($this, 'project', "Project: {$this->name}");
            $this->forceFill(['account_id' => $account->id])->saveQuietly();

            return $account;
        }

        return Account::withoutGlobalScopes()->findOrFail($this->account_id);
    }

    /**
     * @return array{contributions: int, income: int, expenses: int, profit: int, distributed: int}
     */
    public function profitAndLoss(): array
    {
        $totals = ProjectEntry::query()
            ->where('project_id', $this->id)
            ->selectRaw('kind, SUM(amount) as total')
            ->groupBy('kind')
            ->pluck('total', 'kind')
            ->map(fn ($v) => (int) $v);

        $income = ($totals['income'] ?? 0) + ($totals['sale'] ?? 0);
        $expenses = $totals['expense'] ?? 0;

        return [
            'contributions' => $totals['contribution'] ?? 0,
            'income' => $income,
            'expenses' => $expenses,
            'profit' => $income - $expenses,
            'distributed' => $totals['distribution'] ?? 0,
        ];
    }
}
