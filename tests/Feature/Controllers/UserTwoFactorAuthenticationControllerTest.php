<?php

declare(strict_types=1);

use App\Models\User;

it('renders two factor authentication page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('dashboard')
        ->get(route('two-factor.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-two-factor-authentication/show')
            ->has('twoFactorEnabled'));
});

it('shows two factor disabled when not enabled', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('dashboard')
        ->get(route('two-factor.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-two-factor-authentication/show')
            ->where('twoFactorEnabled', false));
});

it('shows two factor enabled when enabled', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('dashboard')
        ->get(route('two-factor.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-two-factor-authentication/show')
            ->where('twoFactorEnabled', true));
});

it('returns qr code when password is confirmed and user has secret', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->getJson(route('two-factor.qr-code'));

    $response->assertOk()
        ->assertJsonStructure(['svg']);
});

it('returns secret key when password is confirmed and user has secret', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->getJson(route('two-factor.secret-key'));

    $response->assertOk()
        ->assertJsonStructure(['secretKey']);
});

it('denies qr code when password confirmation is missing', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('two-factor.qr-code'));

    $response->assertStatus(423);
});

it('denies secret key when password confirmation is missing', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('two-factor.secret-key'));

    $response->assertStatus(423);
});

it('returns recovery codes when password is confirmed and user has recovery codes', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->getJson(route('two-factor.recovery-codes'));

    $response->assertOk()
        ->assertExactJson(['code1', 'code2']);
});

it('denies recovery codes when password confirmation is missing', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
    ]);

    $this->actingAs($user);

    $response = $this->getJson(route('two-factor.recovery-codes'));

    $response->assertStatus(423);
});

it('redirects to password confirmation when visiting two factor page without confirmed password', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('two-factor.show'));

    $response->assertRedirect(route('password.confirm'));

    expect(session('url.intended'))->toBe(route('two-factor.show'));
});
