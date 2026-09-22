<?php

declare(strict_types=1);

use App\Enums\Module;

test('all seven optional modules are defined in Module enum', function (): void {
    $cases = Module::cases();

    expect($cases)->toHaveCount(7)
        ->and(array_column($cases, 'value'))->toBe([
            'authorization',
            'settings',
            'user-management',
            'localization',
            'notifications',
            'audit-trails',
            'reporting',
        ]);
});

test('each module has non-empty label and description', function (): void {
    foreach (Module::cases() as $module) {
        expect($module->label())->toBeString()->not->toBeEmpty()
            ->and($module->description())->toBeString()->not->toBeEmpty();
    }
});

test('module dependencies match the architectural specification', function (): void {
    expect(Module::Authorization->dependencies())->toBe([])
        ->and(Module::Settings->dependencies())->toBe([])
        ->and(Module::UserManagement->dependencies())->toBe([Module::Authorization])
        ->and(Module::Localization->dependencies())->toBe([])
        ->and(Module::Notifications->dependencies())->toBe([Module::Localization])
        ->and(Module::AuditTrails->dependencies())->toBe([])
        ->and(Module::Reporting->dependencies())->toBe([
            Module::Authorization,
            Module::AuditTrails,
            Module::UserManagement,
        ]);
});

test('each module has a defined chisel tag', function (): void {
    expect(Module::Authorization->chiselTag())->toBe('roles-permissions')
        ->and(Module::Settings->chiselTag())->toBe('settings')
        ->and(Module::UserManagement->chiselTag())->toBe('user-management')
        ->and(Module::Localization->chiselTag())->toBe('localization')
        ->and(Module::Notifications->chiselTag())->toBe('notifications')
        ->and(Module::AuditTrails->chiselTag())->toBe('audit-trails')
        ->and(Module::Reporting->chiselTag())->toBe('reporting');
});

test('every owned file declared by every module exists in the repository', function (): void {
    foreach (Module::cases() as $module) {
        foreach ($module->ownedFiles() as $file) {
            expect(file_exists(base_path($file)))
                ->toBeTrue("Owned file [{$file}] declared in module [{$module->value}] does not exist.");
        }
    }
});

test('every shared file declared by every module exists in the repository', function (): void {
    foreach (Module::cases() as $module) {
        foreach ($module->sharedFiles() as $file) {
            expect(file_exists(base_path($file)))
                ->toBeTrue("Shared file [{$file}] declared in module [{$module->value}] does not exist.");
        }
    }
});

test('options and default values return all cases', function (): void {
    expect(Module::options())->toHaveCount(7)
        ->and(Module::options()['authorization'])->toBe('Authorization')
        ->and(Module::options()['reporting'])->toBe('Reporting')
        ->and(Module::defaultValues())->toBe([
            'authorization',
            'settings',
            'user-management',
            'localization',
            'notifications',
            'audit-trails',
            'reporting',
        ]);
});

test('composer packages are correctly mapped to owning modules', function (): void {
    expect(Module::Authorization->composerPackages())->toBe(['spatie/laravel-permission'])
        ->and(Module::Localization->composerPackages())->toBe(['erag/laravel-lang-sync-inertia'])
        ->and(Module::UserManagement->composerPackages())->toBe(['spatie/laravel-data'])
        ->and(Module::Reporting->composerPackages())->toBe(['spatie/laravel-data'])
        ->and(Module::Settings->composerPackages())->toBe([]);
});

test('npm packages are correctly mapped to owning modules', function (): void {
    expect(Module::Localization->npmPackages())->toBe(['@erag/lang-sync-inertia'])
        ->and(Module::Reporting->npmPackages())->toBe([])
        ->and(Module::Authorization->npmPackages())->toBe([]);
});

test('permissions are correctly mapped to owning modules', function (): void {
    expect(Module::Authorization->permissions())->toBe(['authorization.manage'])
        ->and(Module::Settings->permissions())->toBe(['settings.manage'])
        ->and(Module::UserManagement->permissions())->toBe([
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.manage-roles',
            'users.manage-password',
        ])
        ->and(Module::Localization->permissions())->toBe([])
        ->and(Module::Notifications->permissions())->toBe([])
        ->and(Module::AuditTrails->permissions())->toBe(['audit.view'])
        ->and(Module::Reporting->permissions())->toBe([
            'reports.view',
            'reports.export',
        ]);
});

test('routes are correctly mapped to owning modules', function (): void {
    expect(Module::Authorization->routes())->toBe([])
        ->and(Module::Settings->routes())->toBe([
            'settings.edit',
            'settings.update',
        ])
        ->and(Module::UserManagement->routes())->toBe([
            'users.index',
            'users.create',
            'users.store',
            'users.edit',
            'users.update',
            'users.activate',
            'users.deactivate',
            'users.password.update',
            'users.destroy',
        ])
        ->and(Module::Localization->routes())->toBe([
            'locale.update',
        ])
        ->and(Module::Notifications->routes())->toBe([
            'notifications.index',
            'notifications.mark-read',
            'notifications.mark-all-read',
            'notifications.destroy',
            'notification-preferences.edit',
            'notification-preferences.update',
        ])
        ->and(Module::AuditTrails->routes())->toBe([
            'audit-trails.index',
        ])
        ->and(Module::Reporting->routes())->toBe([
            'reports.index',
            'reports.users',
            'reports.users.export',
            'reports.audit',
            'reports.audit.export',
        ]);
});
