<?php

declare(strict_types=1);

use App\Actions\ResolveLocale;
use App\Enums\Locale;
use App\Models\User;
use Illuminate\Http\Request;

test('resolves authenticated user explicit locale', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $action = resolve(ResolveLocale::class);

    expect($action->handle($request))->toBe(Locale::French);
});

test('resolves to default locale when authenticated user has null locale', function (): void {
    $user = User::factory()->create(['locale' => null]);
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    $action = resolve(ResolveLocale::class);

    expect($action->handle($request))->toBe(Locale::default());
});

test('resolves guest locale from valid cookie', function (): void {
    $request = Request::create('/');
    $request->cookies->set('locale', 'ar');

    $action = resolve(ResolveLocale::class);

    expect($action->handle($request))->toBe(Locale::Arabic);
});

test('resolves to default locale for guest without cookie', function (): void {
    $request = Request::create('/');

    $action = resolve(ResolveLocale::class);

    expect($action->handle($request))->toBe(Locale::default());
});

test('resolves to default locale for guest with invalid cookie', function (): void {
    $request = Request::create('/');
    $request->cookies->set('locale', 'invalid');

    $action = resolve(ResolveLocale::class);

    expect($action->handle($request))->toBe(Locale::default());
});

test('authenticated user locale overrides guest cookie', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);
    $request->cookies->set('locale', 'ar');

    $action = resolve(ResolveLocale::class);

    expect($action->handle($request))->toBe(Locale::French);
});
