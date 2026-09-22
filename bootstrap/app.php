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
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state'/* @chisel-localization */, 'locale'/* @end-chisel-localization */]);

        $middleware->web(append: [
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
        //
    })->create();
