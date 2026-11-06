<?php

namespace App\Domain\Projects\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Payouts\Models\Payout;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a project's cost register: what was bought or paid for, how
 * much of it, at what price, and how it was paid.
 *
 * paid_by:
 * - fund: paid from the project fund through a payout (two approvals)
 * - owed: bought on credit; the payout is requested later
 * - member: a member paid for it, which counts as their contribution
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $project_season_id
 * @property int|null $project_plot_id
 * @property string $category
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property int $unit_cost
 * @property int $amount
 * @property CarbonImmutable $incurred_on
 * @property string|null $supplier_name
 * @property string|null $supplier_phone
 * @property string|null $receipt_ref
 * @property string|null $labour_type casual|contract|member
 * @property int|null $workers
 * @property string|null $days
 * @property string $paid_by fund|owed|member
 * @property int|null $member_id
 * @property int|null $payout_id
 * @property int|null $recorded_by
 * @property-read Project $project
 * @property-read ProjectSeason|null $season
 * @property-read ProjectPlot|null $plot
 * @property-read Member|null $member
 * @property-read Payout|null $payout
 */
class ProjectCost extends Model
{
    public const CATEGORIES = [
        'land_lease' => 'Land lease',
        'land_prep' => 'Land preparation',
        'seeds' => 'Seeds and seedlings',
        'fertiliser' => 'Fertiliser and manure',
        'chemicals' => 'Chemicals and sprays',
        'labour' => 'Labour',
        'equipment' => 'Equipment hire',
        'transport' => 'Transport',
        'water' => 'Water and irrigation',
        'feed' => 'Animal feed',
        'vet' => 'Vet and animal health',
        'packaging' => 'Packaging',
        'marketing' => 'Marketing and selling',
        'fees' => 'Licences and fees',
        'other' => 'Other',
    ];

    public const UNITS = [
        'acres', 'hectares', 'kg', 'bags', 'litres', 'packets', 'pieces',
        'seedlings', 'trips', 'hours', 'days', 'months', 'item',
    ];

    public const LABOUR_TYPES = [
        'casual' => 'Casual',
        'contract' => 'Contract',
        'member' => "Members' own labour",
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'integer',
            'amount' => 'integer',
            'incurred_on' => 'immutable_date',
            'workers' => 'integer',
            'days' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProjectSeason, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(ProjectSeason::class, 'project_season_id');
    }

    /** @return BelongsTo<ProjectPlot, $this> */
    public function plot(): BelongsTo
    {
        return $this->belongsTo(ProjectPlot::class, 'project_plot_id');
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Payout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class)->withoutGlobalScopes();
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * paid | awaiting | rejected | owed | in_kind
     */
    public function state(): string
    {
        return match ($this->paid_by) {
            'member' => 'in_kind',
            'owed' => 'owed',
            default => match ($this->payout?->status) {
                null, 'paid' => 'paid',
                'rejected', 'cancelled' => 'rejected',
                default => 'awaiting',
            },
        };
    }

    /** A cost whose payout was turned down is left out of the totals. */
    public function counts(): bool
    {
        return $this->state() !== 'rejected';
    }
}
