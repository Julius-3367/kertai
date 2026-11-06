<?php

namespace App\Domain\Projects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a season plans to spend on one cost category.
 *
 * @property int $id
 * @property int $project_season_id
 * @property string $category
 * @property int $amount
 */
class ProjectBudgetLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /** @return BelongsTo<ProjectSeason, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(ProjectSeason::class, 'project_season_id');
    }
}
