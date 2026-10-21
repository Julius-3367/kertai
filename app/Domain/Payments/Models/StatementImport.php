<?php

namespace App\Domain\Payments\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property string $filename
 * @property int $rows
 * @property int $created
 * @property int $matched
 * @property int $duplicates
 * @property int|null $imported_by
 */
class StatementImport extends Model
{
    use BelongsToGroup;

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
