<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every signed-in page needs the user to belong to a group through a member
 * record; everything is scoped by it.
 */
class EnsureGroupMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->group_id === null || $user->member === null)) {
            abort(403, 'Your login is not linked to a member of the group. Ask the secretary to link it.');
        }

        return $next($request);
    }
}
