<?php

namespace App\Domain\Projects\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property int $project_item_id
 * @property int|null $member_id
 * @property int $quantity
 * @property int $unit_price
 * @property int $total
 * @property int $paid
 * @property string $buyer_name
 * @property string|null $buyer_phone
 * @property string $status unpaid|partial|paid
 * @property CarbonImmutable $sold_on
 * @property-read Project $project
 * @property-read ProjectItem $item
 */
class Sale extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total' => 'integer',
            'paid' => 'integer',
            'sold_on' => 'immutable_date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProjectItem::class, 'project_item_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function balance(): int
    {
        return max(0, $this->total - $this->paid);
    }

    public function recordPayment(int $amount, Payment $payment): void
    {
        $this->paid += $amount;
        $this->status = $this->paid >= $this->total ? 'paid' : 'partial';
        $this->save();

        ProjectEntry::create([
            'project_id' => $this->project_id,
            'kind' => 'sale',
            'amount' => $amount,
            'entry_on' => now()->toDateString(),
            'description' => "Sale of {$this->quantity} {$this->item->unit} {$this->item->name} to {$this->buyer_name}",
            'member_id' => $this->member_id,
            'payment_id' => $payment->id,
            'sale_id' => $this->id,
        ]);
    }
}
