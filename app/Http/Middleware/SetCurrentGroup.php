<?php

namespace App\Http\Middleware;

use App\Domain\Groups\CurrentGroup;
use App\Domain\Groups\Models\Group;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed-in users act for their own group. Public pages (fundraisers, the
 * landing page) fall back to the only group when there is just one.
 */
class SetCurrentGroup
{
    public function __construct(private readonly CurrentGroup $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $group = $request->user()?->group;

        if ($group === null && Group::query()->count() === 1) {
            $group = Group::query()->first();
        }

        $this->current->set($group);

        return $next($request);
    }
}
