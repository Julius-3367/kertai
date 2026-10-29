<?php

namespace App\Domain\Votes\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A question put to every active member between meetings. Votes are
 * secret: officials see who voted, never what they chose.
 *
 * @property int $id
 * @property int $group_id
 * @property string $question
 * @property string|null $details
 * @property list<string> $options
 * @property Carbon $closes_at
 * @property Carbon|null $closed_at
 * @property int|null $created_by
 * @property-read User|null $creator
 */
class Poll extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return HasMany<PollVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null && $this->closes_at->isFuture();
    }

    /**
     * @return list<array{option: string, votes: int}>
     */
    public function tally(): array
    {
        $counts = $this->votes()->selectRaw('choice, count(*) as total')->groupBy('choice')->pluck('total', 'choice');

        return array_map(
            fn (string $option, int $i) => ['option' => $option, 'votes' => (int) ($counts[$i] ?? 0)],
            $this->options,
            array_keys($this->options),
        );
    }
}
