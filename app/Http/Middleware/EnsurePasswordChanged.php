<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logins issued by an official carry a one-time password. Until it is
 * changed, every page sends the member to the change-password screen.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs('password.first', 'password.first.update', 'logout')) {
            return redirect()->route('password.first');
        }

        return $next($request);
    }
}
