<?php

namespace App\Domain\Payouts\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Ledger\Models\Account;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Money going out. Needs two approvals from different officials who hold
 * the approve permission, neither of them the requester, before it can be
 * marked paid.
 *
 * @property int $id
 * @property int $group_id
 * @property int $number
 * @property string $type
 * @property string $status pending|approved|rejected|paid|cancelled
 * @property int $amount
 * @property int $account_id
 * @property int|null $payee_member_id
 * @property string $payee_name
 * @property string|null $payee_phone
 * @property string $description
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property string|null $batch
 * @property int|null $requested_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property Carbon|null $paid_at
 * @property string|null $paid_method
 * @property string|null $paid_reference
 * @property int|null $paid_by
 * @property int|null $journal_entry_id
 * @property-read Account $account
 * @property-read Member|null $payee
 */
class Payout extends Model
{
    use BelongsToGroup;

    public const TYPES = [
        'mgr' => 'Merry-go-round',
        'welfare' => 'Welfare benefit',
        'project_expense' => 'Project expense',
        'fundraiser' => 'Fundraiser disbursement',
        'exit_refund' => 'Exit refund',
        'share_out' => 'Share-out',
        'expense' => 'Group expense',
        'other' => 'Other',
    ];

    public const METHODS = ['mpesa' => 'M-Pesa', 'bank' => 'Bank transfer', 'cash' => 'Cash'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'payee_member_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PayoutApproval::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Pending payouts this official may still decide on: not their own
     * request, not paid to them, and not already decided by them. A rule-
     * raised payout has no requester (NULL), which != would silently drop.
     */
    public function scopeAwaitingDecisionBy(Builder $query, User $user): Builder
    {
        return $query->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('requested_by')->orWhere('requested_by', '!=', $user->id))
            ->where(fn ($q) => $q->whereNull('payee_member_id')->orWhere('payee_member_id', '!=', $user->member?->id ?? 0))
            ->whereDoesntHave('approvals', fn ($a) => $a->where('user_id', $user->id));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
