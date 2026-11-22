<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners\AuditTrails;

use App\Enums\AuditEvent;
use App\Listeners\AuditTrails\AuditAuthenticationEvents;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Features;
use RuntimeException;

it('subscribes to expected authentication events', function (): void {
    $listener = new AuditAuthenticationEvents;
    $events = $listener->subscribe();

    expect($events)->toHaveKey(Login::class, 'onLogin')
        ->and($events)->toHaveKey(Failed::class, 'onFailed')
        ->and($events)->toHaveKey(Lockout::class, 'onLockout')
        ->and($events)->toHaveKey(Logout::class, 'onLogout')
        ->and($events)->toHaveKey(PasswordReset::class, 'onPasswordReset')
        ->and($events)->toHaveKey(TwoFactorAuthenticationEnabled::class, 'onTwoFactorEnabled')
        ->and($events)->toHaveKey(TwoFactorAuthenticationConfirmed::class, 'onTwoFactorConfirmed')
        ->and($events)->toHaveKey(TwoFactorAuthenticationDisabled::class, 'onTwoFactorDisabled')
        ->and($events)->toHaveKey(RecoveryCodeReplaced::class, 'onRecoveryCodeReplaced')
        ->and($events)->toHaveKey(TwoFactorAuthenticationFailed::class, 'onTwoFactorAuthenticationFailed');
});

it('records auth.login event with actor and guard', function (): void {
    $user = User::factory()->create();
    $event = new Login('web', $user, false);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onLogin($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLogin->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('actor.name'))->toBe($user->name)
        ->and($audit->getProperty('actor.email'))->toBe($user->email)
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->attribute_changes?->get('attributes')['guard'])->toBe('web')
        ->and($audit->getProperty('tags'))->toBe(['auth', 'login']);
});

it('records auth.login_failed with known user and throttle outcome without credentials', function (): void {
    $user = User::factory()->create(['email' => 'known@example.com']);
    $request = Request::create('/login', 'POST', ['email' => 'known@example.com']);
    app()->instance('request', $request);

    $throttleKey = 'known@example.com|127.0.0.1';
    RateLimiter::hit($throttleKey);

    $event = new Failed('web', $user, ['email' => 'known@example.com', 'password' => 'secret123']);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onFailed($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('actor.name'))->toBe($user->name)
        ->and($audit->getProperty('actor.email'))->toBe($user->email)
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->attribute_changes?->get('attributes')['guard'])->toBe('web')
        ->and($audit->attribute_changes?->get('attributes')['throttled'])->toBeFalse()
        ->and($audit->attribute_changes?->get('attributes')['attempts'])->toBe(1)
        ->and(json_encode($audit->attribute_changes?->get('attributes')))->not->toContain('secret123');

    RateLimiter::clear($throttleKey);
});

it('uses configured rate limit when determining throttled state on failed login', function (): void {
    config()->set('fortify.rate_limits.login', 3);

    $user = User::factory()->create(['email' => 'custom-audit@example.com']);
    $request = Request::create('/login', 'POST', ['email' => 'custom-audit@example.com']);
    app()->instance('request', $request);

    $throttleKey = 'custom-audit@example.com|127.0.0.1';
    RateLimiter::hit($throttleKey);
    RateLimiter::hit($throttleKey);
    RateLimiter::hit($throttleKey);

    $event = new Failed('web', $user, ['email' => 'custom-audit@example.com', 'password' => 'secret123']);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onFailed($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->attribute_changes?->get('attributes')['throttled'])->toBeTrue()
        ->and($audit->attribute_changes?->get('attributes')['attempts'])->toBe(3);

    RateLimiter::clear($throttleKey);
});

it('records auth.login_failed with unknown user without storing submitted email address', function (): void {
    $request = Request::create('/login', 'POST', ['email' => 'attacker-probe@unknown.com']);
    app()->instance('request', $request);

    $event = new Failed('web', null, ['email' => 'attacker-probe@unknown.com', 'password' => 'secret123']);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onFailed($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBeNull()
        ->and($audit->getProperty('actor.name'))->toBeNull()
        ->and($audit->getProperty('actor.email'))->toBeNull()
        ->and($audit->subject_id)->toBeNull()
        ->and($audit->attribute_changes?->get('attributes')['guard'])->toBe('web')
        ->and(json_encode($audit->attribute_changes?->get('attributes')))->not->toContain('attacker-probe@unknown.com')
        ->and(json_encode($audit->attribute_changes?->get('attributes')))->not->toContain('secret123');
});

it('resolves user by email in handleFailed if event user is null but user exists', function (): void {
    $user = User::factory()->create(['email' => 'registered@example.com']);

    $event = new Failed('web', null, ['email' => 'registered@example.com']);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onFailed($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('actor.email'))->toBe($user->email);
});

it('records auth.lockout with known user', function (): void {
    $user = User::factory()->create(['email' => 'target@example.com']);
    $request = Request::create('/login', 'POST', ['email' => 'target@example.com']);
    $event = new Lockout($request);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onLockout($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLockout->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('actor.email'))->toBe($user->email)
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->attribute_changes?->get('attributes')['throttled'])->toBeTrue()
        ->and($audit->getProperty('tags'))->toBe(['auth', 'lockout']);
});

it('records auth.lockout with unknown user without storing submitted email', function (): void {
    $request = Request::create('/login', 'POST', ['email' => 'unknown@example.com']);
    $event = new Lockout($request);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onLockout($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLockout->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBeNull()
        ->and($audit->getProperty('actor.email'))->toBeNull()
        ->and($audit->subject_id)->toBeNull()
        ->and($audit->attribute_changes?->get('attributes')['throttled'])->toBeTrue()
        ->and(json_encode($audit->attribute_changes?->get('attributes')))->not->toContain('unknown@example.com');
});

it('records auth.logout event with actor', function (): void {
    $user = User::factory()->create();
    $event = new Logout('web', $user);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onLogout($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLogout->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('actor.name'))->toBe($user->name)
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->attribute_changes?->get('attributes')['guard'])->toBe('web')
        ->and($audit->getProperty('tags'))->toBe(['auth', 'logout']);
});

it('records auth.password_reset event with actor and without credentials', function (): void {
    $user = User::factory()->create();
    $event = new PasswordReset($user);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onPasswordReset($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthPasswordReset->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->attribute_changes?->get('attributes'))->toBeNull()
        ->and($audit->attribute_changes?->get('old'))->toBeNull()
        ->and($audit->getProperty('tags'))->toBe(['auth', 'password']);
});

it('records auth.two_factor_enabled event on confirmation', function (): void {
    $user = User::factory()->create();
    $event = new TwoFactorAuthenticationConfirmed($user);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onTwoFactorConfirmed($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthTwoFactorEnabled->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->getProperty('tags'))->toBe(['auth', 'two-factor']);
});

it('ignores TwoFactorAuthenticationEnabled when confirmation is required', function (): void {
    config()->set('fortify.features', [
        Features::twoFactorAuthentication(['confirm' => true]),
    ]);

    $user = User::factory()->create();
    $event = new TwoFactorAuthenticationEnabled($user);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onTwoFactorEnabled($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthTwoFactorEnabled->value)->first();
    expect($audit)->toBeNull();
});

it('records auth.two_factor_enabled when confirmation is not required', function (): void {
    config()->set('fortify.features', [
        Features::twoFactorAuthentication(['confirm' => false]),
    ]);

    $user = User::factory()->create();
    $event = new TwoFactorAuthenticationEnabled($user);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onTwoFactorEnabled($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthTwoFactorEnabled->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id);
});

it('records auth.two_factor_disabled event', function (): void {
    $user = User::factory()->create();
    $event = new TwoFactorAuthenticationDisabled($user);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onTwoFactorDisabled($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthTwoFactorDisabled->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('tags'))->toBe(['auth', 'two-factor']);
});

it('records auth.recovery_codes_used without storing recovery code', function (): void {
    $user = User::factory()->create();
    $secretCode = 'recovery-code-12345';
    $event = new RecoveryCodeReplaced($user, $secretCode);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onRecoveryCodeReplaced($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthRecoveryCodesUsed->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('tags'))->toBe(['auth', 'two-factor'])
        ->and(json_encode($audit->attribute_changes))->not->toContain($secretCode);
});

it('records auth.login_failed on TwoFactorAuthenticationFailed', function (): void {
    $user = User::factory()->create();
    $event = new TwoFactorAuthenticationFailed($user);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onTwoFactorAuthenticationFailed($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->getProperty('tags'))->toBe(['auth', 'login', 'two-factor']);
});

it('omits query strings from URLs recorded for authentication events', function (): void {
    $request = Request::create('/login?signature=secret123&redirect=/dashboard', 'POST', ['email' => 'known@example.com']);
    app()->instance('request', $request);

    $event = new Failed('web', null, ['email' => 'known@example.com']);

    $listener = resolve(AuditAuthenticationEvents::class);
    $listener->onFailed($event);

    $audit = Activity::query()->where('event', AuditEvent::AuthLoginFailed->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->getProperty('request.url'))->toBe('http://localhost/login')
        ->and($audit->getProperty('request.url'))->not->toContain('signature')
        ->and($audit->getProperty('request.url'))->not->toContain('secret123');
});

it('catches and reports audit write exceptions without interrupting authentication', function (): void {
    Exceptions::fake();

    Activity::creating(fn (): never => throw new RuntimeException('Audit DB down'));

    try {
        $listener = resolve(AuditAuthenticationEvents::class);
        $user = User::factory()->create();
        $listener->onLogin(new Login('web', $user, false));

        Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'Audit DB down');
    } finally {
        Activity::flushEventListeners();
    }
});
