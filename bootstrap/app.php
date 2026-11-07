<?php

declare(strict_types=1);

/* @chisel-user-management */
use App\Http\Middleware\EnsureUserIsActive;
/* @end-chisel-user-management */
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
/* @chisel-localization */
use App\Http\Middleware\HandleLocale;
/* @end-chisel-localization */
/* @chisel-settings */
use App\Http\Middleware\HandleSettings;
/* @end-chisel-settings */
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies as FrameworkTrustProxies;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(FrameworkTrustProxies::class, TrustProxies::class);

        $middleware->authenticateSessions();

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state'/* @chisel-localization */, 'locale'/* @end-chisel-localization */]);

        $middleware->web(append: [
            SecurityHeaders::class,
            /* @chisel-settings */
            HandleSettings::class,
            /* @end-chisel-settings */
            HandleAppearance::class,
            /* @chisel-localization */
            HandleLocale::class,
            /* @end-chisel-localization */
            HandleInertiaRequests::class,
            /* @chisel-user-management */
            EnsureUserIsActive::class,
            /* @end-chisel-user-management */
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 500, 503], true)) {
                return $response;
            }

            if (! $request->hasHeader('X-Inertia') && $request->expectsJson()) {
                return $response;
            }

            if (! $request->hasHeader('X-Inertia') && ! $request->acceptsHtml()) {
                return $response;
            }

            if ($status === 500 && config('app.debug')) {
                return $response;
            }

            return Inertia::render('errors/show', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
