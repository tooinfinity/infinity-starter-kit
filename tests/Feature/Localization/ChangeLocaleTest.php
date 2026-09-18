<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;

/* @chisel-localization */

test('authenticated user can change their locale', function (): void {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect();

    $user->refresh();

    expect($user->locale)->toBe(Locale::French);
});

test('authenticated user receives locale cookie', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect()
        ->assertPlainCookie('locale', 'ar');
});

test('invalid locale is rejected', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'invalid'])
        ->assertSessionHasErrors('locale');
});

test('missing locale is rejected', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.update'), [])
        ->assertSessionHasErrors('locale');
});

test('guest can change locale via cookie', function (): void {
    $this->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect()
        ->assertPlainCookie('locale', 'fr');
});

test('guest invalid locale is rejected', function (): void {
    $this->post(route('locale.update'), ['locale' => 'xx'])
        ->assertSessionHasErrors('locale');
});

test('changing locale does not modify unrelated user attributes', function (): void {
    $user = User::factory()->create(['locale' => null]);
    $originalName = $user->name;
    $originalEmail = $user->email;

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'ar']);

    $user->refresh();

    expect($user->name)->toBe($originalName)
        ->and($user->email)->toBe($originalEmail)
        ->and($user->locale)->toBe(Locale::Arabic);
});

test('next request uses the selected locale', function (): void {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'fr']);

    $user->refresh();

    expect($user->locale)->toBe(Locale::French);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(app()->getLocale())->toBe('fr');
});

/* @end-chisel-localization */
