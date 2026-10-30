<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('renders registration page', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('register'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('user/create'));
});

it('may register a new user', function (): void {
    Event::fake([Registered::class]);

    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

    $response->assertRedirectToRoute('dashboard');

    $user = User::query()->where('email', 'test@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and(Hash::check('password1234', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);

    Event::assertDispatched(Registered::class);
});

/* @chisel-localization */
it('persists guest locale cookie on registration', function (): void {
    Event::fake([Registered::class]);

    $this->withUnencryptedCookie('locale', 'fr')
        ->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'French User',
            'email' => 'french@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

    $user = User::query()->where('email', 'french@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->locale?->value)->toBe('fr');
});

it('ignores invalid guest locale cookie on registration', function (mixed $localeCookie): void {
    Event::fake([Registered::class]);

    $this->withUnencryptedCookies(['locale' => $localeCookie])
        ->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'invalid-locale@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

    $user = User::query()->where('email', 'invalid-locale@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->locale)->toBeNull();
})->with([
    'invalid string' => 'xx',
    'array payload' => fn (): array => ['a'],
]);

it('registers user with null locale when guest locale cookie is missing', function (): void {
    Event::fake([Registered::class]);

    $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'No Locale User',
            'email' => 'nolocale@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

    $user = User::query()->where('email', 'nolocale@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->locale)->toBeNull();
});

/* @end-chisel-localization */

it('requires name', function (): void {
    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('name');
});

it('requires email', function (): void {
    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('email');
});

it('requires valid email', function (): void {
    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('email');
});

it('requires unique email', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('email');
});

it('requires password', function (): void {
    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('password');
});

it('requires password confirmation', function (): void {
    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('password');
});

it('requires matching password confirmation', function (): void {
    $response = $this->fromRoute('register')
        ->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
        ]);

    $response->assertRedirectToRoute('register')
        ->assertSessionHasErrors('password');
});

it('redirects authenticated users away from registration', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('register'));

    $response->assertRedirectToRoute('dashboard');
});
