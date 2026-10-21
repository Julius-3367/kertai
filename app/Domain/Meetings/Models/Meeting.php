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
 * @property string|null $online_link Google Meet, Zoom or Teams link
 * @property int $duration_minutes
 * @property string|null $agenda
 * @property string|null $minutes
 * @property string $status scheduled|held|cancelled
 * @property CarbonImmutable|null $reminded_at
 * @property int|null $created_by
 */
class Meeting extends Model
{
    use BelongsToGroup;

    public const KINDS = ['ordinary' => 'Ordinary meeting', 'agm' => 'Annual general meeting', 'special' => 'Special meeting', 'committee' => 'Committee meeting'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
        ];
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

    public function endsAt(): CarbonImmutable
    {
        return $this->scheduled_at->addMinutes($this->duration_minutes ?: 120);
    }

    /**
     * Where to be, in words: the venue, the online link, or both.
     */
    public function place(): string
    {
        return collect([$this->venue, $this->online_link ? 'online' : null])->filter()->implode(' and ') ?: 'the usual venue';
    }

    /**
     * Where and how to join, for message templates ({venue}, {join}).
     *
     * @return array{venue: string, join: string, join_sw: string}
     */
    public function messagePlaceholders(): array
    {
        return [
            'venue' => $this->place(),
            'join' => $this->online_link ? " Join: {$this->online_link}" : '',
            'join_sw' => $this->online_link ? " Jiunge: {$this->online_link}" : '',
        ];
    }
}
