<?php

namespace App\Domain\Members\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Welfare beneficiary: spouse, child or parent of a member.
 *
 * @property int $id
 * @property int $member_id
 * @property string $name
 * @property string $relationship
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $phone
 */
class Dependant extends Model
{
    public const RELATIONSHIPS = ['spouse', 'child', 'parent'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'immutable_date'];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
