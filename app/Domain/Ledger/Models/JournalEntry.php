<?php

namespace App\Domain\Ledger\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Posted entries never change. Mistakes are corrected with a reversal.
 *
 * @property int $id
 * @property int $group_id
 * @property int $number
 * @property CarbonImmutable $entry_date
 * @property string $description
 * @property string|null $reference
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reversal_of_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 */
class JournalEntry extends Model
{
    use BelongsToGroup;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['entry_date' => 'immutable_date'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Journal entries are immutable. Post a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Journal entries are immutable. Post a reversal instead.'));
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<self, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return HasOne<self, $this> */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function total(): int
    {
        return (int) $this->lines->sum('debit');
    }
}
