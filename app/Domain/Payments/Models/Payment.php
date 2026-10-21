<?php

namespace App\Domain\Payments\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Money coming in, from any channel. A payment moves the ledger only once,
 * when it has succeeded and is allocated.
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $member_id
 * @property string $channel simulinkpay|paybill|cash|manual
 * @property string $status pending|processing|succeeded|failed|expired|cancelled
 * @property string $purpose contribution|fundraiser|project|sale|hire|donation
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property int $amount
 * @property string|null $phone
 * @property string|null $payer_name
 * @property string|null $reference
 * @property string|null $external_id
 * @property string|null $receipt_number
 * @property string|null $idempotency_key
 * @property bool $show_name
 * @property Carbon|null $received_at
 * @property string|null $failure_reason
 * @property int|null $meeting_id
 * @property int|null $recorded_by
 * @property int|null $journal_entry_id
 * @property Carbon|null $allocated_at
 * @property array<string, mixed>|null $meta
 * @property-read Member|null $member
 */
class Payment extends Model
{
    use BelongsToGroup;

    public const CHANNELS = ['simulinkpay', 'paybill', 'cash', 'manual'];

    public const PURPOSES = ['contribution', 'fundraiser', 'project', 'sale', 'hire', 'donation'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'show_name' => 'boolean',
            'received_at' => 'datetime',
            'allocated_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return MorphTo<Model, $this> */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', 'succeeded');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUnallocated(Builder $query): Builder
    {
        return $query->where('status', 'succeeded')->whereNull('allocated_at');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['succeeded', 'failed', 'expired', 'cancelled'], true);
    }

    public function purposeLabel(): string
    {
        return match ($this->purpose) {
            'contribution' => 'contributions',
            'fundraiser' => 'the fundraiser',
            'project' => 'project contribution',
            'sale' => 'a purchase',
            'hire' => 'hire',
            default => 'a donation',
        };
    }
}
