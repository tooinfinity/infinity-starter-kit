<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;

/* @chisel-localization */

test('Inertia shared props contain locale', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')
        );
});

test('Inertia shared props contain direction', function (): void {
    $user = User::factory()->withLocale(Locale::Arabic)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('direction', 'rtl')
        );
});

test('Inertia shared props contain supportedLocales', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('supportedLocales', 3)
            ->where('supportedLocales.0.code', 'en')
            ->where('supportedLocales.0.name', 'English')
            ->where('supportedLocales.0.nativeName', 'English')
            ->where('supportedLocales.0.direction', 'ltr')
            ->where('supportedLocales.1.code', 'fr')
            ->where('supportedLocales.1.nativeName', 'Français')
            ->where('supportedLocales.2.code', 'ar')
            ->where('supportedLocales.2.nativeName', 'العربية')
            ->where('supportedLocales.2.direction', 'rtl')
        );
});

test('default locale is English when user has no preference', function (): void {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('direction', 'ltr')
        );
});

test('LTR direction for French locale', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')
            ->where('direction', 'ltr')
        );
});

/* @end-chisel-localization */
