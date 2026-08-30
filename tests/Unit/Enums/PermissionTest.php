<?php

declare(strict_types=1);

use App\Enums\Permission;

/* @chisel-roles-permissions */

test('all permission values are unique', function (): void {
    $values = Permission::values();

    expect($values)->toHaveCount(count(array_unique($values)));
});

test('values helper returns all permission strings', function (): void {
    $values = Permission::values();

    expect($values)->toBe([
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        /* @chisel-settings */
        'settings.manage',
        /* @end-chisel-settings */
    ]);
});

test('each permission is a string-backed enum', function (): void {
    foreach (Permission::cases() as $case) {
        expect($case->value)->toBeString();
    }
});

/* @end-chisel-roles-permissions */
