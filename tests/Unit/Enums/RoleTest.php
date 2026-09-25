<?php

declare(strict_types=1);

use App\Enums\Role;

test('super admin role has the expected value', function (): void {
    expect(Role::SuperAdmin->value)->toBe('super-admin');
});

test('role enum is string-backed', function (): void {
    foreach (Role::cases() as $case) {
        expect($case->value)->toBeString();
    }
});
