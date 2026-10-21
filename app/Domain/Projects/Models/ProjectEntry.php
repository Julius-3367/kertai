<?php

namespace App\Domain\Projects\Models;

use App\Domain\Members\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The project's own book: contributions, income, sales, expenses and
 * profit distributions. Each one also has a ledger entry.
 *
 * @property int $id
 * @property int $project_id
 * @property string $kind contribution|income|sale|expense|distribution
 * @property int $amount
 * @property CarbonImmutable $entry_on
 * @property string $description
 * @property int|null $member_id
 * @property int|null $payment_id
 * @property int|null $payout_id
 * @property int|null $sale_id
 */
class ProjectEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'entry_on' => 'immutable_date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
