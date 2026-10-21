<?php

namespace App\Domain\Projects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock the project sells, such as coffee seedlings.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $unit
 * @property int $unit_price
 * @property int $quantity
 */
class ProjectItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['unit_price' => 'integer', 'quantity' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }
}
