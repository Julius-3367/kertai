<?php

namespace App\Domain\Documents\Models;

use App\Domain\Access\Models\Position;
use App\Domain\Groups\Concerns\BelongsToGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recurring obligations: certificate renewal with Social Services, AGM,
 * annual returns, audit of books.
 *
 * @property int $id
 * @property int $group_id
 * @property string $title
 * @property string|null $description
 * @property CarbonImmutable $due_on
 * @property string $recurrence none|monthly|yearly
 * @property int|null $position_id
 * @property string $status pending|done
 * @property CarbonImmutable|null $completed_on
 * @property CarbonImmutable|null $reminded_at
 */
class ComplianceItem extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_on' => 'immutable_date',
            'completed_on' => 'immutable_date',
            'reminded_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
