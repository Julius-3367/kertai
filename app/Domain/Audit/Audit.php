<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Groups\CurrentGroup;
use Illuminate\Database\Eloquent\Model;

final class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $groupId = null): void
    {
        $groupId ??= app(CurrentGroup::class)->id() ?? $subject?->getAttribute('group_id');

        AuditLog::withoutGlobalScopes()->create([
            'group_id' => $groupId,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 255),
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
