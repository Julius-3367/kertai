<?php

namespace App\Domain\Fundraisers\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Domain\Ledger\ChartOfAccounts;
use App\Domain\Ledger\Models\Account;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $group_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $beneficiary
 * @property int $target
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property string $status draft|active|closed
 * @property bool $show_contributors
 * @property int|null $account_id
 * @property int|null $created_by
 */
class Fundraiser extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'target' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'show_contributors' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return MorphMany<Payment, $this> */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /** @return HasMany<FundraiserPledge, $this> */
    public function pledges(): HasMany
    {
        return $this->hasMany(FundraiserPledge::class)->latest();
    }

    public function fundAccount(): Account
    {
        if ($this->account_id === null) {
            $account = app(ChartOfAccounts::class)->openFor($this, 'fundraiser', "Fundraiser: {$this->title}");
            $this->forceFill(['account_id' => $account->id])->saveQuietly();

            return $account;
        }

        return Account::withoutGlobalScopes()->findOrFail($this->account_id);
    }

    public function raised(): int
    {
        return (int) $this->payments()->withoutGlobalScopes()->where('status', 'succeeded')->sum('amount');
    }

    public function isOpen(): bool
    {
        return $this->status === 'active'
            && $this->starts_on->lte(CarbonImmutable::today())
            && ($this->ends_on === null || $this->ends_on->gte(CarbonImmutable::today()));
    }
}
