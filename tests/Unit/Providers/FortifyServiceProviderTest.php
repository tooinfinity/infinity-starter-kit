<?php

declare(strict_types=1);

use App\Providers\FortifyServiceProvider;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Fortify;

test('FortifyServiceProvider ignores routes when login limiter is not configured', function (): void {
    config()->set('fortify.limiters.login');

    $provider = new FortifyServiceProvider(app());
    $provider->register();

    $reflection = new ReflectionProperty(Fortify::class, 'registersRoutes');
    expect($reflection->getValue())->toBeFalse();

    // Reset for subsequent tests
    $reflection->setValue(null, true);
    config()->set('fortify.limiters.login', 'login');
});

/* @chisel-user-management */
test('FortifyServiceProvider boots password confirmation routes when login limiter is not configured', function (): void {
    config()->set('fortify.limiters.login');

    $provider = new FortifyServiceProvider(app());
    $provider->boot();

    expect(Route::has('password.confirm'))->toBeTrue()
        ->and(Route::has('password.confirm.store'))->toBeTrue()
        ->and(Route::has('password.confirmation'))->toBeTrue();

    config()->set('fortify.limiters.login', 'login');
});
/* @end-chisel-user-management */
