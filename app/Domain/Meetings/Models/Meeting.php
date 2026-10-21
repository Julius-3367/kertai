<?php

namespace App\Domain\Meetings\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $group_id
 * @property string $title
 * @property string $kind ordinary|agm|special|committee
 * @property CarbonImmutable $scheduled_at
 * @property string|null $venue
 * @property string|null $agenda
 * @property string|null $minutes
 * @property string $status scheduled|held|cancelled
 * @property int|null $created_by
 */
class Meeting extends Model
{
    use BelongsToGroup;

    public const KINDS = ['ordinary' => 'Ordinary meeting', 'agm' => 'Annual general meeting', 'special' => 'Special meeting', 'committee' => 'Committee meeting'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'immutable_datetime'];
    }

    /** @return HasMany<MeetingAttendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    /** @return HasMany<Resolution, $this> */
    public function resolutions(): HasMany
    {
        return $this->hasMany(Resolution::class)->orderBy('number');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
