<?php

namespace App\Domain\Groups\Concerns;

use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current group and stamps group_id on create.
 * Outside a group context (webhooks, console before a group is set) the
 * scope is inactive, so callers there must filter explicitly.
 */
trait BelongsToGroup
{
    public static function bootBelongsToGroup(): void
    {
        static::addGlobalScope('group', function (Builder $builder) {
            $groupId = app(CurrentGroup::class)->id();

            if ($groupId !== null) {
                $builder->where($builder->qualifyColumn('group_id'), $groupId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->group_id)) {
                $model->group_id = app(CurrentGroup::class)->id();
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
