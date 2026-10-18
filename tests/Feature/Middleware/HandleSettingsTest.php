<?php

declare(strict_types=1);

use App\Actions\GetSetting;
use App\Actions\UpdateSettings;
use App\Enums\SettingKey;
use App\Http\Middleware\HandleSettings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

it('applies configured application name and display timezone to config without mutating app.timezone', function (): void {
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationName, 'Acme Corp');
    resolve(UpdateSettings::class)->set(SettingKey::ApplicationTimezone, 'Africa/Algiers');

    $originalAppTimezone = config('app.timezone');

    $middleware = new HandleSettings(resolve(GetSetting::class));
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

it('retains default config when loading settings throws an exception', function (): void {
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('Settings unreadable'));

    $originalName = config('app.name');
    $originalTimezone = config('app.timezone');

    $middleware = resolve(HandleSettings::class);
    $request = Request::create('/', 'GET');

    $response = $middleware->handle($request, fn (Request $req): Response => new Response('OK'));

    expect($response->getContent())->toBe('OK')
        ->and(config('app.name'))->toBe($originalName)
        ->and(config('app.timezone'))->toBe($originalTimezone);
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
