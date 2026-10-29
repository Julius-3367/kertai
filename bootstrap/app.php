<?php

use App\Http\Middleware\EnsureGroupMember;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentGroup;
use App\Support\RuleViolation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['kertai-theme', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetCurrentGroup::class,
            EnsurePasswordChanged::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'member' => EnsureGroupMember::class,
        ]);

        // The group scope must be on before route models are looked up,
        // otherwise /members/{id} could load another group's member.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentGroup::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('webhooks/*') || $request->expectsJson(),
        );

        // A rule that blocked a button press goes back to the page as a
        // red toast and a form error, never as an error page. It is an
        // expected outcome, so it stays out of the error log.
        $exceptions->dontReport(RuleViolation::class);

        $exceptions->render(function (RuleViolation $e, Request $request) {
            if ($request->expectsJson() || $request->is('webhooks/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back()->withErrors(['action' => $e->getMessage()])->withInput();
        });

        // Show errors inside the app instead of Laravel's bare error page,
        // which Inertia would pop up in a modal. Crash pages stay as they
        // are while debugging.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || $request->is('api/*') || $request->is('webhooks/*')) {
                return $response;
            }

            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'The page had expired. Please try again.']);

                return back();
            }

            $crash = in_array($status, [500, 503], true) && ! app()->hasDebugModeEnabled();

            if (! in_array($status, [403, 404], true) && ! $crash) {
                return $response;
            }

            // Keep a specific reason (e.g. a login not linked to a member),
            // drop the framework's generic wording.
            $message = $status === 403 && ! in_array($e->getMessage(), ['', 'This action is unauthorized.', 'Forbidden'], true)
                ? $e->getMessage()
                : null;

            return Inertia::render('error', ['status' => $status, 'message' => $message])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
