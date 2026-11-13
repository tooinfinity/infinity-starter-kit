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

test('all role values are unique and kebab-cased', function (): void {
    $values = array_column(Role::cases(), 'value');

    expect($values)->toHaveCount(count(array_unique($values)));

    foreach ($values as $value) {
        expect($value)->toMatch('/^[a-z]+(-[a-z]+)*$/');
    }
});
