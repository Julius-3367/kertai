<?php

namespace App\Domain\Groups;

use App\Domain\Groups\Models\Group;
use RuntimeException;

/**
 * The group the current request or job is acting for. Models using
 * BelongsToGroup are scoped to it and get its id on create.
 */
final class CurrentGroup
{
    private ?Group $group = null;

    public function set(?Group $group): void
    {
        $this->group = $group;
    }

    public function get(): ?Group
    {
        return $this->group;
    }

    public function id(): ?int
    {
        return $this->group?->id;
    }

    public function require(): Group
    {
        return $this->group ?? throw new RuntimeException('No current group is set.');
    }

    /**
     * Run a callback with another group as current, then restore.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(Group $group, callable $callback): mixed
    {
        $previous = $this->group;
        $this->group = $group;

        try {
            return $callback();
        } finally {
            $this->group = $previous;
        }
    }
}
