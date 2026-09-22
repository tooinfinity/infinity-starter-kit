<?php

declare(strict_types=1);

use App\Enums\Module;
use App\Exceptions\CircularDependencyException;
use App\Exceptions\DependentModuleException;
use App\Support\Chisel\ModuleResolver;

test('normalize converts unified optional_modules answers', function (): void {
    $normalized = ModuleResolver::normalize([
        'optional_modules' => ['reporting', 'settings'],
    ]);

    expect($normalized)->toBe([Module::Reporting, Module::Settings]);
});

test('normalize handles legacy authorization_features and application_features answers', function (): void {
    $normalized = ModuleResolver::normalize([
        'authorization_features' => ['roles-permissions'],
        'application_features' => ['settings', 'audit-trails'],
    ]);

    expect($normalized)->toContain(Module::Authorization)
        ->and($normalized)->toContain(Module::Settings)
        ->and($normalized)->toContain(Module::AuditTrails)
        ->and($normalized)->toHaveCount(3);
});

test('resolve automatically includes mandatory dependencies', function (): void {
    // User management requires Authorization
    $resolvedUserMgmt = ModuleResolver::resolve(['user-management']);
    expect($resolvedUserMgmt)->toContain(Module::Authorization)
        ->and($resolvedUserMgmt)->toContain(Module::UserManagement);

    // Notifications requires Localization
    $resolvedNotifications = ModuleResolver::resolve(['notifications']);
    expect($resolvedNotifications)->toContain(Module::Localization)
        ->and($resolvedNotifications)->toContain(Module::Notifications);

    // Reporting requires AuditTrails, Authorization, and UserManagement
    $resolvedReporting = ModuleResolver::resolve(['reporting']);
    expect($resolvedReporting)->toContain(Module::Authorization)
        ->and($resolvedReporting)->toContain(Module::AuditTrails)
        ->and($resolvedReporting)->toContain(Module::UserManagement)
        ->and($resolvedReporting)->toContain(Module::Reporting);
});

test('resolve returns modules in topological order', function (): void {
    $resolved = ModuleResolver::resolve(['reporting']);

    $positions = [];
    foreach ($resolved as $index => $module) {
        $positions[$module->value] = $index;
    }

    // Dependencies must precede dependents
    expect($positions['authorization'])->toBeLessThan($positions['user-management'])
        ->and($positions['authorization'])->toBeLessThan($positions['reporting'])
        ->and($positions['audit-trails'])->toBeLessThan($positions['reporting'])
        ->and($positions['user-management'])->toBeLessThan($positions['reporting']);
});

test('unselected returns unchosen modules in reverse topological order', function (): void {
    $unselected = ModuleResolver::unselected(['settings']);

    // Reporting (which depends on others) must come before AuditTrails and Authorization
    $positions = [];
    foreach ($unselected as $index => $module) {
        $positions[$module->value] = $index;
    }

    expect($positions['reporting'])->toBeLessThan($positions['audit-trails'])
        ->and($positions['reporting'])->toBeLessThan($positions['user-management'])
        ->and($positions['user-management'])->toBeLessThan($positions['authorization']);
});

test('validateRemoval rejects removal when dependents are present', function (): void {
    $installed = [
        Module::Authorization,
        Module::AuditTrails,
        Module::UserManagement,
        Module::Reporting,
    ];

    expect(fn () => ModuleResolver::validateRemoval(Module::AuditTrails, $installed))
        ->toThrow(DependentModuleException::class, 'Cannot remove Audit Trails because Reporting depends on it. Remove Reporting first.')
        ->and(fn () => ModuleResolver::validateRemoval(Module::Authorization, $installed))
        ->toThrow(DependentModuleException::class, 'Cannot remove Authorization because User Management and Reporting depend on it. Remove User Management and Reporting first.');
});

test('validateRemoval succeeds when no installed modules depend on target', function (): void {
    $installed = [
        Module::Authorization,
        Module::AuditTrails,
        Module::UserManagement,
        Module::Reporting,
    ];

    // Removing reporting is valid because nothing depends on it
    ModuleResolver::validateRemoval(Module::Reporting, $installed);
    expect(true)->toBeTrue();
});

test('exclusiveComposerPackages identifies packages not required by remaining modules', function (): void {
    // If removing Authorization, spatie/laravel-permission is exclusive
    $packages = ModuleResolver::exclusiveComposerPackages([Module::Authorization], [Module::Settings]);
    expect($packages)->toBe(['spatie/laravel-permission']);

    // If removing Reporting while UserManagement remains, spatie/laravel-data is NOT exclusive
    $packages = ModuleResolver::exclusiveComposerPackages([Module::Reporting], [Module::UserManagement]);
    expect($packages)->toBe([]);

    // If removing both Reporting and UserManagement, spatie/laravel-data IS exclusive
    $packages = ModuleResolver::exclusiveComposerPackages([Module::Reporting, Module::UserManagement], [Module::Settings]);
    expect($packages)->toBe(['spatie/laravel-data']);
});

test('exclusiveNpmPackages identifies packages not required by remaining modules', function (): void {
    $packages = ModuleResolver::exclusiveNpmPackages([Module::Localization], [Module::Settings, Module::Authorization]);
    expect($packages)->toBe(['@erag/lang-sync-inertia']);

    $retainedPackages = ModuleResolver::exclusiveNpmPackages([Module::Localization], [Module::Localization]);
    expect($retainedPackages)->toBe([]);
});

test('hasDependency evaluates direct and indirect dependencies correctly', function (): void {
    expect(ModuleResolver::hasDependency(Module::Reporting, Module::Authorization))->toBeTrue()
        ->and(ModuleResolver::hasDependency(Module::Notifications, Module::Localization))->toBeTrue()
        ->and(ModuleResolver::hasDependency(Module::Settings, Module::Authorization))->toBeFalse()
        ->and(ModuleResolver::hasDependency(Module::Reporting, Module::Localization))->toBeFalse();
});

test('normalize handles non-string values and roles-permissions alias', function (): void {
    // Non-string entries are ignored (line 296) and 'roles-permissions' resolves to Authorization (line 301)
    $normalized = ModuleResolver::normalize(['roles-permissions', 123, null]);
    expect($normalized)->toBe([Module::Authorization]);

    $fromOptional = ModuleResolver::normalize([
        'optional_modules' => ['roles-permissions', 456],
    ]);
    expect($fromOptional)->toBe([Module::Authorization]);
});

test('topologicalSort throws CircularDependencyException when cycle is detected', function (): void {
    expect(fn (): array => ModuleResolver::topologicalSort(
        [Module::Settings, Module::Localization],
        fn (Module $m): array => match ($m) {
            Module::Settings => [Module::Localization],
            Module::Localization => [Module::Settings],
            default => [],
        },
    ))->toThrow(CircularDependencyException::class, 'Circular dependency detected involving module');
});
