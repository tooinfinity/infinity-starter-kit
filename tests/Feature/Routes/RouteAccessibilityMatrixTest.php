<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/**
 * @return list<string>
 */
function getGuestGetRoutes(): array
{
    $routes = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $isGuest = collect($middleware)->contains(fn ($m): bool => is_string($m) && (str_starts_with($m, 'guest') || str_contains($m, '\RedirectIfAuthenticated')));

        // Skip two-factor-challenge as it requires a pending login in session
        if ($isGuest && ! str_contains($route->uri(), '{') && $route->uri() !== 'two-factor-challenge') {
            $routes[] = $route->uri();
        }
    }

    return array_values(array_unique($routes));
}

/**
 * @return list<string>
 */
function getAuthGetRoutes(): array
{
    $routes = [];

    /** @var RoutingRoute $route */
    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $uri = $route->uri();
        if (str_starts_with($uri, '_') || str_starts_with($uri, 'storage') || str_contains($uri, '{')) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $isAuth = collect($middleware)->contains(fn ($m): bool => is_string($m) && (str_starts_with($m, 'auth') || str_contains($m, '\Authenticate')));

        // Skip settings/two-factor which requires password.confirm
        if ($isAuth && $uri !== 'settings/two-factor') {
            $routes[] = $uri;
        }
    }

    return array_values(array_unique($routes));
}

test('guest get routes redirect authenticated users to dashboard', function (): void {
    $guestRoutes = getGuestGetRoutes();

    if ($guestRoutes === []) {
        expect(true)->toBeTrue();

        return;
    }

    $user = User::factory()->create();

    foreach ($guestRoutes as $uri) {
        $response = $this->actingAs($user)->get($uri);
        $response->assertRedirectToRoute('dashboard');
    }
});

test('auth get routes redirect guests to login', function (): void {
    $authRoutes = getAuthGetRoutes();

    if ($authRoutes === []) {
        expect(true)->toBeTrue();

        return;
    }

    foreach ($authRoutes as $uri) {
        $response = $this->get($uri);
        $response->assertRedirectToRoute('login');
    }
});

test('public home route is accessible by both guests and authenticated users', function (): void {
    $this->get('/')->assertOk();

    $user = User::factory()->create();
    $this->actingAs($user)->get('/')->assertOk();
});
