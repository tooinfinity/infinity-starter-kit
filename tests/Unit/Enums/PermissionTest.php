<?php

declare(strict_types=1);

use App\Enums\Permission;

test('all permission values are unique', function (): void {
    $values = Permission::values();

    expect($values)->toHaveCount(count(array_unique($values)));
});

test('values helper returns all permission strings', function (): void {
    $values = Permission::values();

    expect($values)->toBe([
        'authorization.manage',
        /* @chisel-user-management */
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.manage-roles',
        'users.manage-password',
        /* @end-chisel-user-management */
        /* @chisel-settings */
        'settings.manage',
        /* @end-chisel-settings */
        /* @chisel-audit-trails */
        'audit.view',
        /* @end-chisel-audit-trails */
        /* @chisel-reporting */
        'reports.view',
        'reports.export',
        /* @end-chisel-reporting */
    ]);

});

test('each permission is a string-backed enum', function (): void {
    foreach (Permission::cases() as $case) {
        expect($case->value)->toBeString();
    }
});
