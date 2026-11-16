<?php

declare(strict_types=1);

use App\Http\Requests\CreateSessionRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
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

it('creates a session upon valid credentials', function (bool $remember): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $payload = [
        'email' => 'test@example.com',
        'password' => 'password',
    ];

    if ($remember) {
        $payload['remember'] = true;
    }

    $response = $this->fromRoute('login')
        ->post(route('login.store'), $payload);

    $response->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
})->with([
    'without remember me' => false,
    'with remember me' => true,
]);

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

it('validates required fields on login', function (array $payload, string $errorField): void {
    $response = $this->fromRoute('login')
        ->post(route('login.store'), $payload);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors($errorField);
})->with([
    'missing email' => [
        ['password' => 'password'],
        'email',
    ],
    'missing password' => [
        ['email' => 'test@example.com'],
        'password',
    ],
]);

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

it('locks out on the 4th attempt when RATE_LIMIT_LOGIN is 3', function (): void {
    config()->set('fortify.rate_limits.login', 3);

    User::factory()->create([
        'email' => 'custom-limit@example.com',
        'password' => Hash::make('password'),
    ]);

    for ($i = 0; $i < 3; $i++) {
        $this->fromRoute('login')
            ->post(route('login.store'), [
                'email' => 'custom-limit@example.com',
                'password' => 'wrong-password',
            ]);
    }

    $response = $this->fromRoute('login')
        ->post(route('login.store'), [
            'email' => 'custom-limit@example.com',
            'password' => 'wrong-password',
        ]);

    $response->assertRedirectToRoute('login')
        ->assertSessionHasErrors('email');

    $errors = session('errors');
    expect($errors->get('email')[0])->toContain('Too many login attempts');
});

it('derives normalized throttle key for given email and ip', function (): void {
    expect(CreateSessionRequest::throttleKeyFor('  User@Example.COM  ', '192.0.2.1'))
        ->toBe('user@example.com|192.0.2.1');
});

it('resolves configured max login attempts', function (): void {
    config()->set('fortify.rate_limits.login', 10);
    expect(CreateSessionRequest::maxAttempts())->toBe(10);

    config()->set('fortify.rate_limits.login', 5);
    expect(CreateSessionRequest::maxAttempts())->toBe(5);
});
