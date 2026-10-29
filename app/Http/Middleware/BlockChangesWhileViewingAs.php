<?php

namespace App\Http\Middleware;

use App\Domain\Access\ViewAs;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "View as" is look-only: nothing can be saved until the preview ends.
 */
class BlockChangesWhileViewingAs
{
    private const ALLOWED = ['view-as.stop', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! ViewAs::active() || $request->isMethodSafe() || in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        $message = 'You are viewing Kertai as '.ViewAs::label().'. Exit the preview to make changes.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 423);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return back();
    }
}
