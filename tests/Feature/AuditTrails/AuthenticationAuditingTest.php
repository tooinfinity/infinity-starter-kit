<?php

declare(strict_types=1);

namespace Tests\Feature\AuditTrails;

/* @chisel-login */
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
/* @end-chisel-login */
/* @chisel-password-reset */
use Illuminate\Support\Facades\Password;
/* @end-chisel-password-reset */
/* @chisel-two-factor */
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use PragmaRX\Google2FA\Google2FA;

/* @end-chisel-two-factor */

/* @chisel-login */
test('successful login records auth.login audit trail with IP and user agent', function (): void {
    $user = User::factory()/* @chisel-two-factor */ ->withoutTwoFactor()/* @end-chisel-two-factor */ ->create([
        'email' => 'login-audit@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '198.51.100.42',
        'HTTP_USER_AGENT' => 'AuditBrowser/1.0',
    ])->post(route('login.store'), [
        'email' => 'login-audit@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirectToRoute('dashboard');

    expect(Activity::query()->where('event', AuditEvent::AuthLogin->value)->count())->toBe(1);

    $activity = Activity::query()->where('event', AuditEvent::AuthLogin->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->actor_name)->toBe($user->name)
        ->and($activity->actor_email)->toBe($user->email)
        ->and($activity->ip_address)->toBe('198.51.100.42')
        ->and($activity->user_agent)->toBe('AuditBrowser/1.0')
        ->and($activity->tags)->toBe(['auth', 'login']);
});

test('failed login with registered email records auth.login_failed with user_id and throttle info', function (): void {
    $user = User::factory()->create([
        'email' => 'registered-fail@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '198.51.100.55',
        'HTTP_USER_AGENT' => 'AuditBrowser/2.0',
    ])->post(route('login.store'), [
        'email' => 'registered-fail@example.com',
        'password' => 'wrong-secret-guess',
    ]);

    $response->assertSessionHasErrors('email');

    $activity = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->actor_name)->toBe($user->name)
        ->and($activity->actor_email)->toBe($user->email)
        ->and($activity->ip_address)->toBe('198.51.100.55')
        ->and($activity->user_agent)->toBe('AuditBrowser/2.0')
        ->and($activity->new_values['guard'])->toBe('web')
        ->and($activity->new_values['throttled'])->toBeFalse()
        ->and($activity->new_values['attempts'])->toBe(1)
        ->and(json_encode($activity->new_values))->not->toContain('wrong-secret-guess');
});

test('failed login with unknown email does not persist submitted address', function (): void {
    $attackerSubmittedEmail = 'not-a-real-user-12345@example.org';

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_USER_AGENT' => 'MaliciousClient/1.0',
    ])->post(route('login.store'), [
        'email' => $attackerSubmittedEmail,
        'password' => 'random-password',
    ]);

    $response->assertSessionHasErrors('email');

    $activity = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBeNull()
        ->and($activity->actor_name)->toBeNull()
        ->and($activity->actor_email)->toBeNull()
        ->and($activity->subject_id)->toBeNull()
        ->and($activity->ip_address)->toBe('203.0.113.10')
        ->and($activity->user_agent)->toBe('MaliciousClient/1.0')
        ->and(json_encode($activity->old_values))->not->toContain($attackerSubmittedEmail)
        ->and(json_encode($activity->new_values))->not->toContain($attackerSubmittedEmail);
});

test('lockout records auth.lockout audit trail', function (): void {
    $user = User::factory()->create([
        'email' => 'lockout-target@example.com',
        'password' => Hash::make('password'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.99',
        ])->post(route('login.store'), [
            'email' => 'lockout-target@example.com',
            'password' => 'wrong',
        ]);
    }

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '198.51.100.99',
    ])->post(route('login.store'), [
        'email' => 'lockout-target@example.com',
        'password' => 'wrong',
    ]);

    $response->assertSessionHasErrors('email');

    expect(Activity::query()->where('event', AuditEvent::AuthLockout->value)->count())->toBe(1);

    $activity = Activity::query()->where('event', AuditEvent::AuthLockout->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->actor_email)->toBe($user->email)
        ->and($activity->new_values['throttled'])->toBeTrue()
        ->and($activity->tags)->toBe(['auth', 'lockout']);
});

test('failed login audit trail throttled flag respects configured rate limit', function (): void {
    config()->set('fortify.rate_limits.login', 3);

    $user = User::factory()->create([
        'email' => 'custom-throttle@example.com',
        'password' => Hash::make('password'),
    ]);

    for ($i = 0; $i < 3; $i++) {
        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.123',
        ])->post(route('login.store'), [
            'email' => 'custom-throttle@example.com',
            'password' => 'wrong',
        ]);
    }

    $activities = Activity::query()
        ->where('event', AuditEvent::AuthLoginFailed->value)
        ->where('properties->actor->email', 'custom-throttle@example.com')
        ->orderBy('id')
        ->get();

    expect($activities)->toHaveCount(3)
        ->and($activities[0]->new_values['throttled'])->toBeFalse()
        ->and($activities[1]->new_values['throttled'])->toBeFalse()
        ->and($activities[2]->new_values['throttled'])->toBeTrue()
        ->and($activities[2]->new_values['attempts'])->toBe(3);
});
/* @end-chisel-login */

/* @chisel-logout */
test('logout records auth.logout audit trail', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.77',
            'HTTP_USER_AGENT' => 'LogoutAgent/1.0',
        ])
        ->post(route('logout'));

    $response->assertRedirect('/');

    expect(Activity::query()->where('event', AuditEvent::AuthLogout->value)->count())->toBe(1);

    $activity = Activity::query()->where('event', AuditEvent::AuthLogout->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->actor_name)->toBe($user->name)
        ->and($activity->ip_address)->toBe('198.51.100.77')
        ->and($activity->tags)->toBe(['auth', 'logout']);
});
/* @end-chisel-logout */

/* @chisel-password-reset */
test('password reset via emailed link records auth.password_reset without sensitive values', function (): void {
    $user = User::factory()->create(['email' => 'reset-flow@example.com']);
    $token = Password::broker()->createToken($user);

    $response = $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'reset-flow@example.com',
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ]);

    $response->assertRedirectToRoute('login');

    expect(Activity::query()->where('event', AuditEvent::AuthPasswordReset->value)->count())->toBe(1);

    $activity = Activity::query()->where('event', AuditEvent::AuthPasswordReset->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->tags)->toBe(['auth', 'password'])
        ->and(json_encode($activity->new_values))->not->toContain('NewSecurePassword123!')
        ->and(json_encode($activity->new_values))->not->toContain($token);
});
/* @end-chisel-password-reset */

/* @chisel-two-factor */
test('two-factor confirmation records auth.two_factor_enabled', function (): void {
    $engine = new Google2FA;
    $secret = $engine->generateSecretKey();
    $validCode = $engine->getCurrentOtp($secret);

    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->session(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.confirm'), [
            'code' => $validCode,
        ]);

    $response->assertSessionHasNoErrors();

    expect(Activity::query()->where('event', AuditEvent::AuthTwoFactorEnabled->value)->count())->toBe(1);

    $activity = Activity::query()->where('event', AuditEvent::AuthTwoFactorEnabled->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->tags)->toBe(['auth', 'two-factor']);
});

test('two-factor disable records auth.two_factor_disabled', function (): void {
    $user = User::factory()->create();

    event(new TwoFactorAuthenticationDisabled($user));

    expect(Activity::query()->where('event', AuditEvent::AuthTwoFactorDisabled->value)->count())->toBe(1);

    $activity = Activity::query()->where('event', AuditEvent::AuthTwoFactorDisabled->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->tags)->toBe(['auth', 'two-factor']);
});

test('recovery code usage records auth.recovery_codes_used without storing code', function (): void {
    $user = User::factory()->create();
    $usedRecoveryCode = 'code-xyz-999';

    event(new RecoveryCodeReplaced($user, $usedRecoveryCode));

    expect(Activity::query()->where('event', AuditEvent::AuthRecoveryCodesUsed->value)->count())->toBe(1);

    $activity = Activity::query()->where('event', AuditEvent::AuthRecoveryCodesUsed->value)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->tags)->toBe(['auth', 'two-factor'])
        ->and(json_encode($activity->old_values))->not->toContain($usedRecoveryCode)
        ->and(json_encode($activity->new_values))->not->toContain($usedRecoveryCode);
});
/* @end-chisel-two-factor */
