<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;

test('middleware sets App locale for authenticated user with explicit locale', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();

    $this->actingAs($user)
        ->get(route('dashboard'));

    expect(app()->getLocale())->toBe('fr');
});

test('middleware uses default locale when user has no locale preference', function (): void {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'));

    expect(app()->getLocale())->toBe('en');
});

test('middleware sets App locale for guest with valid cookie', function (): void {
    $this->withUnencryptedCookie('locale', 'ar')
        ->get(route('home'));

    expect(app()->getLocale())->toBe('ar');
});

test('middleware uses default locale for guest without cookie', function (): void {
    $this->get(route('home'));

    expect(app()->getLocale())->toBe('en');
});

test('middleware ignores invalid locale cookie', function (): void {
    $this->withUnencryptedCookie('locale', 'invalid')
        ->get(route('home'));

    expect(app()->getLocale())->toBe('en');
});

test('middleware sets Arabic RTL direction', function (): void {
    $user = User::factory()->withLocale(Locale::Arabic)->create();

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    expect(app()->getLocale())->toBe('ar');
});
