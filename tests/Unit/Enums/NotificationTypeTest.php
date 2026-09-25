<?php

declare(strict_types=1);

use App\Enums\NotificationType;

test('NotificationType enum has all expected cases', function (): void {
    $cases = NotificationType::cases();

    expect($cases)->toHaveCount(3)
        ->and(NotificationType::Security->value)->toBe('security')
        ->and(NotificationType::UserManagement->value)->toBe('user_management')
        ->and(NotificationType::System->value)->toBe('system');
});

test('NotificationType::values() returns all values', function (): void {
    expect(NotificationType::values())->toBe(['security', 'user_management', 'system']);
});

test('NotificationType::label() returns human readable labels', function (): void {
    expect(NotificationType::Security->label())->toBe('Security')
        ->and(NotificationType::UserManagement->label())->toBe('User Management')
        ->and(NotificationType::System->label())->toBe('System');
});

test('NotificationType::isMandatory() identifies mandatory types', function (): void {
    expect(NotificationType::Security->isMandatory())->toBeTrue()
        ->and(NotificationType::UserManagement->isMandatory())->toBeFalse()
        ->and(NotificationType::System->isMandatory())->toBeFalse();
});

test('NotificationType can be created from valid string', function (): void {
    expect(NotificationType::tryFrom('security'))->toBe(NotificationType::Security)
        ->and(NotificationType::tryFrom('user_management'))->toBe(NotificationType::UserManagement)
        ->and(NotificationType::tryFrom('system'))->toBe(NotificationType::System);
});

test('NotificationType returns null for invalid string', function (): void {
    expect(NotificationType::tryFrom('invalid'))->toBeNull()
        ->and(NotificationType::tryFrom(''))->toBeNull();
});
