<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

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

it('requires password confirmation to enable two factor authentication', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = $this->actingAs($user)->post(route('two-factor.enable'));

    $response->assertRedirect(route('password.confirm'));

    expect($user->fresh()->two_factor_secret)->toBeNull();
});

it('enables two factor authentication when password is confirmed', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('two-factor.show')->post(route('two-factor.enable'));

    $response->assertRedirectToRoute('two-factor.show');

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

it('disables two factor authentication flow', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('two-factor.show')->delete(route('two-factor.disable'));

    $response->assertRedirectToRoute('two-factor.show');

    $fresh = $user->fresh();
    expect($fresh->two_factor_secret)->toBeNull()
        ->and($fresh->two_factor_recovery_codes)->toBeNull()
        ->and($fresh->two_factor_confirmed_at)->toBeNull();
});

it('regenerates recovery codes for two factor authentication', function (): void {
    $initialCodes = ['code-1', 'code-2'];
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode($initialCodes)),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('two-factor.show')->post(route('two-factor.regenerate-recovery-codes'));

    $response->assertRedirectToRoute('two-factor.show');

    $newCodes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
    expect($newCodes)->toBeArray()
        ->and($newCodes)->not->toBe($initialCodes);
});

it('redirects to login when accessing two factor challenge without pending login', function (): void {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirectToRoute('login');
});

it('fails challenge authentication with invalid code', function (): void {
    $mockProvider = Mockery::mock(TwoFactorAuthenticationProvider::class);
    $mockProvider->shouldReceive('verify')->andReturn(false);
    $this->app->instance(TwoFactorAuthenticationProvider::class, $mockProvider);

    $user = User::factory()->create([
        'two_factor_secret' => encrypt('test-secret-key-16-chars'),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->withSession(['login.id' => $user->getKey()])
        ->post(route('two-factor.login.store'), [
            'code' => '000000',
        ]);

    $response->assertRedirectToRoute('two-factor.login')
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('authenticates user with recovery code and consumes it for single use', function (): void {
    $recoveryCode = 'recovery-code-valid';
    $provider = resolve(TwoFactorAuthenticationProvider::class);
    $user = User::factory()->create([
        'two_factor_secret' => encrypt($provider->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode([$recoveryCode, 'unused-code'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->withSession(['login.id' => $user->getKey(), 'login.remember' => false])
        ->post(route('two-factor.login.store'), [
            'recovery_code' => $recoveryCode,
        ]);

    $response->assertRedirectToRoute('dashboard');
    $this->assertAuthenticatedAs($user);

    // Verify recovery code was consumed (single-use)
    $remainingCodes = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
    expect($remainingCodes)->not->toContain($recoveryCode)
        ->and($remainingCodes)->toContain('unused-code');
});
