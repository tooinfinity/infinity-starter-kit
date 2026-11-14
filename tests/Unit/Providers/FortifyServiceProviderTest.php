<?php

declare(strict_types=1);

use App\Providers\FortifyServiceProvider;
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
