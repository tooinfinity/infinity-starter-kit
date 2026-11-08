<?php

declare(strict_types=1);

use App\Enums\AuditEvent;

test('AuditEvent enum has expected cases and values', function (): void {
    expect(AuditEvent::UserCreated->value)->toBe('user.created')
        ->and(AuditEvent::UserUpdated->value)->toBe('user.updated')
        ->and(AuditEvent::UserActivated->value)->toBe('user.activated')
        ->and(AuditEvent::UserDeactivated->value)->toBe('user.deactivated')
        ->and(AuditEvent::UserDeleted->value)->toBe('user.deleted')
        ->and(AuditEvent::UserPasswordChanged->value)->toBe('user.password_changed')
        ->and(AuditEvent::UserRoleChanged->value)->toBe('user.role_changed')
        ->and(AuditEvent::SettingsUpdated->value)->toBe('settings.updated')
        ->and(AuditEvent::AuthLogin->value)->toBe('auth.login')
        ->and(AuditEvent::AuthLoginFailed->value)->toBe('auth.login_failed')
        ->and(AuditEvent::AuthLockout->value)->toBe('auth.lockout')
        ->and(AuditEvent::AuthLogout->value)->toBe('auth.logout')
        ->and(AuditEvent::AuthPasswordReset->value)->toBe('auth.password_reset')
        ->and(AuditEvent::AuthTwoFactorEnabled->value)->toBe('auth.two_factor_enabled')
        ->and(AuditEvent::AuthTwoFactorDisabled->value)->toBe('auth.two_factor_disabled')
        ->and(AuditEvent::AuthRecoveryCodesUsed->value)->toBe('auth.recovery_codes_used');
});

test('AuditEvent values returns all enum values', function (): void {
    expect(AuditEvent::values())->toBe([
        'user.created',
        'user.updated',
        'user.activated',
        'user.deactivated',
        'user.deleted',
        'user.password_changed',
        'user.role_changed',
        'settings.updated',
        'auth.login',
        'auth.login_failed',
        'auth.lockout',
        'auth.logout',
        'auth.password_reset',
        'auth.two_factor_enabled',
        'auth.two_factor_disabled',
        'auth.recovery_codes_used',
    ]);
});

test('AuditEvent label returns translated string', function (): void {
    foreach (AuditEvent::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
