<?php

namespace App\Domain\Documents\Models;

use App\Domain\Groups\Concerns\BelongsToGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property string $title
 * @property string $category
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property string $visibility members|officials
 * @property int|null $uploaded_by
 */
class Document extends Model
{
    use BelongsToGroup;

    public const CATEGORIES = [
        'constitution' => 'Constitution and by-laws',
        'registration' => 'Registration certificate',
        'minutes' => 'Minutes',
        'financial' => 'Financial statements',
        'agreement' => 'Agreements and contracts',
        'other' => 'Other',
    ];

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
