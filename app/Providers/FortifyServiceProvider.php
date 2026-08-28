<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->bootFortifyDefaults();
        $this->bootRateLimitingDefaults();
    }

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
        /* @chisel-two-factor-authentication */
        Fortify::twoFactorChallengeView(fn () => Inertia::render('user-two-factor-authentication-challenge/show'));
        /* @end-chisel-two-factor-authentication */
        Fortify::confirmPasswordView(fn () => Inertia::render('user-password-confirmation/create'));
    }

    private function bootRateLimitingDefaults(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->string('email')->value().$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));
    }
}
