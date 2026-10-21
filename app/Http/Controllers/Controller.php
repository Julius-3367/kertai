<?php

namespace App\Http\Controllers;

use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use App\Domain\Members\Models\Member;
use App\Support\Money;
use Inertia\Inertia;

abstract class Controller
{
    protected function group(): Group
    {
        return app(CurrentGroup::class)->require();
    }

    protected function member(): ?Member
    {
        return request()->user()?->member;
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }

    protected function cents(mixed $shillings): int
    {
        return Money::toCents($shillings === null ? null : (string) $shillings);
    }

    /**
     * Members for pickers: id, number and name, active first.
     *
     * @return list<array{id: int, label: string, status: string}>
     */
    protected function memberOptions(bool $activeOnly = true): array
    {
        return Member::query()
            ->when($activeOnly, fn ($q) => $q->active())
            ->orderBy('name')
            ->get(['id', 'name', 'member_number', 'status'])
            ->map(fn (Member $m) => ['id' => $m->id, 'label' => "{$m->name} ({$m->member_number})", 'status' => $m->status])
            ->all();
    }
}
