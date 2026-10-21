<?php

namespace App\Domain\Meetings\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A decision of the group. Rule changes and position appointments refer to
 * one of these by number.
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $meeting_id
 * @property string $number
 * @property string $title
 * @property string|null $body
 * @property CarbonImmutable $adopted_on
 */
class Resolution extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['adopted_on' => 'immutable_date'];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
