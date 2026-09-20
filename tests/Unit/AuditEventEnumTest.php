<?php

declare(strict_types=1);

use App\Enums\AuditEvent;

/* @chisel-audit-trails */

test('AuditEvent enum has expected cases and values', function (): void {
    expect(AuditEvent::UserCreated->value)->toBe('user.created')
        ->and(AuditEvent::UserUpdated->value)->toBe('user.updated')
        ->and(AuditEvent::UserActivated->value)->toBe('user.activated')
        ->and(AuditEvent::UserDeactivated->value)->toBe('user.deactivated')
        ->and(AuditEvent::UserDeleted->value)->toBe('user.deleted')
        ->and(AuditEvent::UserPasswordChanged->value)->toBe('user.password_changed')
        ->and(AuditEvent::SettingsUpdated->value)->toBe('settings.updated');
});

test('AuditEvent values returns all enum values', function (): void {
    expect(AuditEvent::values())->toBe([
        'user.created',
        'user.updated',
        'user.activated',
        'user.deactivated',
        'user.deleted',
        'user.password_changed',
        'settings.updated',
    ]);
});

test('AuditEvent label returns translated string', function (): void {
    foreach (AuditEvent::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});

/* @end-chisel-audit-trails */
