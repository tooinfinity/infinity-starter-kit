<?php

declare(strict_types=1);

use App\Actions\UpdateSettings;
use App\Enums\SettingKey;
use App\Http\Middleware\HandleSettings;
use App\Models\User;
use App\Queries\SettingQuery;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Symfony\Component\HttpFoundation\Response;

it('applies configured application name and display timezone to config without mutating app.timezone', function (): void {
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationName, 'Acme Corp');
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationTimezone, 'Africa/Algiers');

    $originalAppTimezone = config('app.timezone');

    $middleware = new HandleSettings(resolve(SettingQuery::class));
    $request = Request::create('/', 'GET');

    $response = $middleware->handle($request, fn (Request $req): Response => new Response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and(config('app.name'))->toBe('Acme Corp')
        ->and(config('app.display_timezone'))->toBe('Africa/Algiers')
        ->and(config('app.timezone'))->toBe($originalAppTimezone);
});

it('shares the configured application name with the frontend', function (): void {
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationName, 'Acme Admin');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('name', 'Acme Admin'));
});

it('shares the configured display timezone with the frontend', function (): void {
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationTimezone, 'Europe/Paris');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('timezone', 'Europe/Paris'));
});

it('never mutates app.timezone at runtime', function (): void {
    $originalTimezone = config('app.timezone');

    resolve(UpdateSettings::class)->set(SettingKey::ApplicationTimezone, 'Asia/Tokyo');

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'));

    expect(config('app.timezone'))->toBe($originalTimezone);
});

it('reads application settings with a single cache lookup per request', function (): void {
    Cache::spy();
    $this->get(route('home'))->assertOk();

    Cache::shouldHaveReceived('remember')->once();
});

it('retains default config and reports exception when loading settings throws a query exception', function (): void {
    Exceptions::fake();
    Cache::shouldReceive('remember')->andThrow(new QueryException('sqlite', 'select * from settings', [], new Exception('table not found')));

    $originalName = config('app.name');
    $originalTimezone = config('app.timezone');

    $middleware = resolve(HandleSettings::class);
    $request = Request::create('/', 'GET');

    $response = $middleware->handle($request, fn (Request $req): Response => new Response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and(config('app.name'))->toBe($originalName)
        ->and(config('app.timezone'))->toBe($originalTimezone);

    Exceptions::assertReported(QueryException::class);
});

it('retains default config and reports exception when cache store throws', function (): void {
    Exceptions::fake();
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('Settings unreadable'));

    $originalName = config('app.name');
    $originalTimezone = config('app.timezone');

    $middleware = resolve(HandleSettings::class);
    $request = Request::create('/', 'GET');

    $response = $middleware->handle($request, fn (Request $req): Response => new Response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and(config('app.name'))->toBe($originalName)
        ->and(config('app.timezone'))->toBe($originalTimezone);

    Exceptions::assertReported(RuntimeException::class);
});

it('returns 200 with default config and reports exception when cache store throws on web request', function (): void {
    Exceptions::fake();
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('Cache store unavailable'));

    $originalName = config('app.name');
    $originalTimezone = config('app.timezone');

    $this->get(route('home'))->assertOk();

    expect(config('app.name'))->toBe($originalName)
        ->and(config('app.timezone'))->toBe($originalTimezone);

    Exceptions::assertReported(RuntimeException::class);
});

it('returns 200 with default config and reports exception when settings table is missing on web request', function (): void {
    Exceptions::fake();
    Cache::shouldReceive('remember')->andThrow(new QueryException('sqlite', 'select * from settings', [], new Exception('table not found')));

    $originalName = config('app.name');
    $originalTimezone = config('app.timezone');

    $this->get(route('home'))->assertOk();

    expect(config('app.name'))->toBe($originalName)
        ->and(config('app.timezone'))->toBe($originalTimezone);

    Exceptions::assertReported(QueryException::class);
});

it('does not swallow unrelated errors such as TypeError', function (): void {
    Cache::shouldReceive('remember')->andThrow(new TypeError('Unrelated type error'));

    $middleware = resolve(HandleSettings::class);
    $request = Request::create('/', 'GET');

    expect(fn () => $middleware->handle($request, fn (Request $req): Response => new Response('OK')))
        ->toThrow(TypeError::class, 'Unrelated type error');
});

it('does not override config when settings are empty strings or not strings', function (): void {
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationName, '');
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationTimezone, 12345);

    $originalName = config('app.name');

    $middleware = resolve(HandleSettings::class);
    $request = Request::create('/', 'GET');

    $response = $middleware->handle($request, fn (Request $req): Response => new Response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and(config('app.name'))->toBe($originalName);
});
