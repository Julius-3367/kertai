<?php

namespace App\Domain\Projects\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One round of a project: a planting season, a batch of chicks, a hire
 * period. Costs and sales are kept per season so each one shows its own
 * profit. Closing a season locks its costs.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string $status open|closed
 * @property string|null $yield_quantity
 * @property string|null $yield_unit
 * @property string|null $notes
 */
class ProjectSeason extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'yield_quantity' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ProjectBudgetLine, $this> */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(ProjectBudgetLine::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function covers(CarbonImmutable $date): bool
    {
        return $date->gte($this->starts_on) && ($this->ends_on === null || $date->lte($this->ends_on));
    }
}
