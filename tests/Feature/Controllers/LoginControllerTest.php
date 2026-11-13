<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('renders login page', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('login'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('session/create')
            ->has('canResetPassword')
            ->has('status'));
});

it('may create a session', function (): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
});

it('may create a session with remember me', function (): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

    $response->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
});

/* @chisel-two-factor */
it('redirects to two-factor challenge when enabled', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('two-factor.login');

    $this->assertGuest();
});
/* @end-chisel-two-factor */

it('fails with invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('requires email', function (): void {
    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('email');
});

it('requires password', function (): void {
    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('password');
});

it('redirects authenticated users away from login', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('login'));

    $response->assertRedirectToRoute('dashboard');
});

it('throttles login attempts after too many failures', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    // Make 5 failed login attempts to trigger rate limiting
    for ($i = 0; $i < 5; $i++) {
        $this->fromRoute('login')
            ->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
    }

    // The 6th attempt should be throttled
    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('email');

    $errors = session('errors');
    expect($errors->get('email')[0])->toContain('Too many login attempts');
});

it('clears rate limit after successful login', function (): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    // Make a few failed attempts
    for ($i = 0; $i < 3; $i++) {
        $this->fromRoute('login')
            ->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
    }

    // Successful login should clear the rate limit
    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('dashboard');
    $this->assertAuthenticatedAs($user);
});

it('dispatches lockout event when rate limit is reached', function (): void {
    Event::fake([Lockout::class]);

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    // Make 6 failed login attempts to trigger rate limiting and Lockout event
    // The Lockout event fires on the 6th attempt when tooManyAttempts returns true
    for ($i = 0; $i < 6; $i++) {
        $this->fromRoute('login')
            ->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);
    }

    Event::assertDispatched(Lockout::class);
});

it('may authenticate with mixed-case and whitespace-padded email', function (string $emailInput): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'john@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => $emailInput,
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('dashboard');
    $this->assertAuthenticatedAs($user);
})->with([
    'mixed case' => 'John@Example.com',
    'uppercase' => 'JOHN@EXAMPLE.COM',
    'leading and trailing whitespace' => '   john@example.com   ',
    'mixed case with whitespace' => '  John@Example.COM  ',
]);

it('shares throttle bucket regardless of email case or whitespace', function (): void {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('password'),
    ]);

    $emails = [
        'john@example.com',
        'John@Example.com',
        '  john@example.com  ',
        'JOHN@EXAMPLE.COM',
        '  John@Example.com  ',
    ];

    foreach ($emails as $email) {
        $this->fromRoute('login')
            ->post(route('login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
    }

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('email');

    $errors = session('errors');
    expect($errors->get('email')[0])->toContain('Too many login attempts');
});

it('fails with unknown email address', function (): void {
    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'nonexistent@example.com',
            'password' => 'secret-password',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('redirects to intended URL after successful login', function (): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'intended@example.com',
        'password' => Hash::make('password123'),
    ]);

    // Access protected route as guest to store intended URL
    $this->get(route('dashboard', ['tab' => 'analytics']))
        ->assertRedirectToRoute('login');

    // Login and verify redirect to intended URL
    $response = $this->post(route('login.store'), [
        'email' => 'intended@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard', ['tab' => 'analytics']));
    $this->assertAuthenticatedAs($user);
});

it('attaches remember cookie when remember flag is provided', function (): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'remember@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'remember@example.com',
        'password' => 'password123',
        'remember' => true,
    ]);

    $response->assertRedirectToRoute('dashboard');
    $this->assertAuthenticatedAs($user);
    $response->assertCookie(Auth::guard()->getRecallerName());
});
