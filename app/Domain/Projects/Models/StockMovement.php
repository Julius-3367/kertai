<?php

namespace App\Domain\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_item_id
 * @property string $kind in|out|adjust|sale
 * @property int $quantity signed
 * @property int|null $unit_cost
 * @property string|null $note
 * @property int|null $sale_id
 * @property int|null $recorded_by
 */
class StockMovement extends Model
{
    protected $guarded = ['id'];

    /** @return BelongsTo<ProjectItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ProjectItem::class, 'project_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
