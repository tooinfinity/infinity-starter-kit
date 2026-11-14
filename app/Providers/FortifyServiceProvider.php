<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
/* @chisel-user-management */
use Illuminate\Support\Facades\Route;
/* @end-chisel-user-management */
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
/* @chisel-user-management */
use Laravel\Fortify\Http\Controllers\ConfirmablePasswordController;
use Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController;
use Laravel\Fortify\RoutePath;

/* @end-chisel-user-management */

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (config('fortify.limiters.login') === null) {
            Fortify::ignoreRoutes();
        }
    }

    public function boot(): void
    {
        $this->bootFortifyDefaults();
        $this->bootRateLimitingDefaults();
        /* @chisel-user-management */
        if (config('fortify.limiters.login') === null) {
            $this->bootPasswordConfirmationRoutes();
        }

        /* @end-chisel-user-management */
    }

    /* @chisel-user-management */
    private function bootPasswordConfirmationRoutes(): void
    {
        $authMiddleware = config()->string('fortify.auth_middleware', 'auth').':'.config()->string('fortify.guard', 'web');

        Route::group(['middleware' => config('fortify.middleware', ['web'])], function () use ($authMiddleware): void {
            Route::get(RoutePath::for('password.confirm', '/user/confirm-password'), [ConfirmablePasswordController::class, 'show'])
                ->middleware([$authMiddleware])
                ->name('password.confirm');

            Route::get(RoutePath::for('password.confirmation', '/user/confirmed-password-status'), [ConfirmedPasswordStatusController::class, 'show'])
                ->middleware([$authMiddleware])
                ->name('password.confirmation');

            Route::post(RoutePath::for('password.confirm', '/user/confirm-password'), [ConfirmablePasswordController::class, 'store'])
                ->middleware([$authMiddleware])
                ->name('password.confirm.store');
        });
    }

    /* @end-chisel-user-management */

    private function bootFortifyDefaults(): void
    {
        /* @chisel-registration */
        Fortify::registerView(fn () => Inertia::render('user/create'));
        /* @end-chisel-registration */
        /* @chisel-email-verification */
        Fortify::verifyEmailView(fn (Request $request) => Inertia::render(
            'user-email-verification-notification/create',
            ['status' => $request->session()->get('status')],
        ));
        /* @end-chisel-email-verification */
        /* @chisel-two-factor */
        Fortify::twoFactorChallengeView(fn () => Inertia::render('user-two-factor-authentication-challenge/show'));
        Fortify::confirmPasswordView(fn () => Inertia::render('user-password-confirmation/create'));
        /* @end-chisel-two-factor */
    }

    private function bootRateLimitingDefaults(): void
    {
        /* @chisel-login */
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->string('email')->value().$request->ip()));
        /* @end-chisel-login */
        /* @chisel-two-factor */
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
        /* @end-chisel-two-factor */
        /* @chisel-registration */
        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute(config()->integer('fortify.rate_limits.register', 5))->by($request->ip()));
        /* @end-chisel-registration */
        /* @chisel-password-reset */
        RateLimiter::for('forgot-password', /** @return array<int, Limit> */ function (Request $request): array {
            $email = $request->string('email')->trim()->lower()->value();
            $limit = config()->integer('fortify.rate_limits.forgot_password', 5);

            return [
                Limit::perMinute($limit)->by('ip:'.$request->ip()),
                Limit::perMinute($limit)->by('email:'.($email !== '' ? $email : $request->ip())),
            ];
        });
        RateLimiter::for('reset-password', fn (Request $request): Limit => Limit::perMinute(config()->integer('fortify.rate_limits.reset_password', 5))->by($request->ip()));
        /* @end-chisel-password-reset */
    }
}
