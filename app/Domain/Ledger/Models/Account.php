<?php

namespace App\Domain\Ledger\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Two kinds of account. "asset" is where money physically sits (bank,
 * SimulinkPay, cash box). "fund" is who the money belongs to or what it is
 * for (savings, welfare, a project). Assets carry debit balances, funds
 * credit balances, and the two totals are always equal.
 *
 * @property int $id
 * @property int $group_id
 * @property string $code
 * @property string $name
 * @property string $kind
 * @property string $purpose
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property bool $is_active
 */
class Account extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isAsset(): bool
    {
        return $this->kind === 'asset';
    }

    /**
     * True for accounts whose balance is tracked per member.
     */
    public function isPerMember(): bool
    {
        return in_array($this->purpose, ['savings', 'prepayments'], true);
    }
}
