<?php

declare(strict_types=1);

use App\Providers\FortifyServiceProvider;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router;
use Laravel\Fortify\Fortify;

test('FortifyServiceProvider ignores routes when fortify.routes is false', function (): void {
    $reflection = new ReflectionProperty(Fortify::class, 'registersRoutes');
    $original = $reflection->getValue();
    $reflection->setValue(null, true);

    try {
        config()->set('fortify.routes', false);

        $provider = new FortifyServiceProvider(app());
        $provider->register();

        expect($reflection->getValue())->toBeFalse();
    } finally {
        $reflection->setValue(null, $original);
        config()->set('fortify.routes', true);
    }
});

test('FortifyServiceProvider preserves route registration when login limiter is null but fortify.routes is true', function (): void {
    $reflection = new ReflectionProperty(Fortify::class, 'registersRoutes');
    $original = $reflection->getValue();
    $reflection->setValue(null, true);

    try {
        config()->set('fortify.routes', true);
        config()->set('fortify.limiters.login');

        $provider = new FortifyServiceProvider(app());
        $provider->register();

        expect($reflection->getValue())->toBeTrue();
    } finally {
        $reflection->setValue(null, $original);
        config()->set('fortify.limiters.login', 'login');
    }
});

/* @chisel-user-management */
test('FortifyServiceProvider boots password confirmation routes when fortify.routes is false', function (): void {
    /** @var Router $router */
    $router = resolve(Router::class);
    $originalRoutes = $router->getRoutes();
    $router->setRoutes(new RouteCollection());

    try {
        expect($router->has('password.confirm'))->toBeFalse()
            ->and($router->has('password.confirm.store'))->toBeFalse()
            ->and($router->has('password.confirmation'))->toBeFalse();

        config()->set('fortify.routes', false);

        $provider = new FortifyServiceProvider(app());
        $provider->boot();

        $router->getRoutes()->refreshNameLookups();

        expect($router->has('password.confirm'))->toBeTrue()
            ->and($router->has('password.confirm.store'))->toBeTrue()
            ->and($router->has('password.confirmation'))->toBeTrue();
    } finally {
        config()->set('fortify.routes', true);
        $router->setRoutes($originalRoutes);
    }
});

test('FortifyServiceProvider applies custom prefix and domain to password confirmation routes when fortify.routes is false', function (): void {
    /** @var Router $router */
    $router = resolve(Router::class);
    $originalRoutes = $router->getRoutes();
    $router->setRoutes(new RouteCollection());

    try {
        config()->set('fortify.routes', false);
        config()->set('fortify.prefix', 'account');
        config()->set('fortify.domain', 'auth.example.com');

        $provider = new FortifyServiceProvider(app());
        $provider->boot();

        $router->getRoutes()->refreshNameLookups();

        $confirmRoute = $router->getRoutes()->getByName('password.confirm');
        expect($confirmRoute)->not->toBeNull()
            ->and($confirmRoute?->uri())->toBe('account/user/confirm-password')
            ->and($confirmRoute?->getDomain())->toBe('auth.example.com');
    } finally {
        config()->set('fortify.routes', true);
        config()->set('fortify.prefix');
        config()->set('fortify.domain');
        $router->setRoutes($originalRoutes);
    }
});
/* @end-chisel-user-management */
