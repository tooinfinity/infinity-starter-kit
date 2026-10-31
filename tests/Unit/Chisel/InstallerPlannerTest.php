<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\InstallerPlanner;

test('installer planner produces accurate plan when all features are selected', function (): void {
    $context = new InstallerContext(
        providedAnswers: [],
        answers: [
            'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
            'optional_modules' => ['authorization', 'settings', 'user-management', 'localization', 'notifications', 'audit-trails', 'reporting'],
        ],
        paths: [],
        selectedModules: ['authorization', 'settings', 'user-management', 'localization', 'notifications', 'audit-trails', 'reporting'],
        selectedAuthFeatures: ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        hasAuthorization: true,
        adminName: 'Root Admin',
        adminEmail: 'root@example.com',
        adminPassword: 'secret-password',
        isNonInteractive: true,
        skipNode: false,
        isMockedScript: false,
        isDryRun: true,
    );

    $plan = InstallerPlanner::plan($context, base_path(), isFresh: true);

    expect($plan->unselectedFeatures)->toBe([])
        ->and($plan->prunedFiles)->toBe([])
        ->and($plan->prunedDirectories)->toBe([])
        ->and($plan->retainedComposerPackages)->toContain('spatie/laravel-permission', 'spatie/laravel-data', 'erag/laravel-lang-sync-inertia')
        ->and($plan->removedComposerPackages)->toBe(['laravel/chisel'])
        ->and($plan->retainedFrontendPackages)->toBe(['@erag/lang-sync-inertia'])
        ->and($plan->removedFrontendPackages)->toBe([])
        ->and($plan->migrationCommand)->toBe('php artisan migrate:fresh --force')
        ->and($plan->configurationPhases['Authorization Setup'])->toContain('authorization:setup')
        ->and($plan->configurationPhases['Administrator Setup'])->toContain('root@example.com')
        ->and($plan->postScaffoldCommands)->toHaveKeys([
            'PHP Code Formatting',
            'Wayfinder Route Generation',
            'Frontend Dependencies',
            'Frontend Build',
            'Frontend Lint',
        ])
        ->and($plan->dependencyDerivedFeatures)->toContain('authorization', 'audit-trails', 'user-management', 'login')
        ->and($plan->requiredBy['authorization'])->toContain('audit-trails', 'reporting', 'settings', 'user-management')
        ->and($plan->requiredBy['audit-trails'])->toBe(['reporting'])
        ->and($plan->requiredBy['user-management'])->toBe(['reporting'])
        ->and($plan->requiredBy['login'])->toContain('logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor');
});

test('installer planner produces accurate plan when minimal features are selected', function (): void {
    $context = new InstallerContext(
        providedAnswers: [],
        answers: [
            'auth_features' => ['login'],
            'optional_modules' => [],
        ],
        paths: [],
        selectedModules: [],
        selectedAuthFeatures: ['login'],
        hasAuthorization: false,
        adminName: null,
        adminEmail: null,
        adminPassword: null,
        isNonInteractive: true,
        skipNode: true,
        isMockedScript: false,
        isDryRun: true,
    );

    $plan = InstallerPlanner::plan($context, base_path(), isFresh: false);

    expect($plan->unselectedFeatures)->toContain('registration', 'two-factor', 'authorization', 'settings', 'reporting')
        ->and($plan->prunedFiles)->toContain('app/Http/Controllers/UserPasswordController.php')
        ->and($plan->prunedFiles)->toContain('config/data.php')
        ->and($plan->prunedFiles)->toContain('tests/Feature/AuditTrails/SettingsAuditingTest.php')
        ->and($plan->prunedDirectories)->toContain('resources/js/pages/user-password')
        ->and($plan->modifiedFiles)->toContain('app/Models/User.php')
        ->and($plan->modifiedFiles)->toContain('phpstan.neon')
        ->and($plan->modifiedFiles)->toContain('routes/web.php')
        ->and($plan->retainedComposerPackages)->toBe([])
        ->and($plan->removedComposerPackages)->toContain('spatie/laravel-permission', 'spatie/laravel-data', 'erag/laravel-lang-sync-inertia', 'laravel/chisel')
        ->and($plan->retainedFrontendPackages)->toBe([])
        ->and($plan->removedFrontendPackages)->toBe(['@erag/lang-sync-inertia'])
        ->and($plan->migrationCommand)->toBe('php artisan migrate --force')
        ->and($plan->configurationPhases['Authorization Setup'])->toContain('Skipped')
        ->and($plan->configurationPhases['Administrator Setup'])->toContain('Skipped')
        ->and($plan->postScaffoldCommands['Frontend Pipeline'])->toContain('Skipped');
});

test('installer planner handles partial features and retains shared packages appropriately', function (): void {
    // Only user-management selected (along with authorization which it depends on)
    $context = new InstallerContext(
        providedAnswers: ['admin' => ['email' => 'admin@test.local']],
        answers: [
            'auth_features' => ['login', 'password-update'],
            'optional_modules' => ['authorization', 'user-management'],
        ],
        paths: [],
        selectedModules: ['authorization', 'user-management'],
        selectedAuthFeatures: ['login', 'password-update'],
        hasAuthorization: true,
        adminName: null,
        adminEmail: 'admin@test.local',
        adminPassword: null,
        isNonInteractive: false,
        skipNode: false,
        isMockedScript: false,
        isDryRun: true,
    );

    $plan = InstallerPlanner::plan($context, base_path());

    // spatie/laravel-data retained because user-management is selected
    expect($plan->retainedComposerPackages)->toContain('spatie/laravel-data', 'spatie/laravel-permission')
        ->and($plan->removedComposerPackages)->not->toContain('spatie/laravel-data')
        // UserPasswordController not pruned because password-update is selected
        ->and($plan->prunedFiles)->not->toContain('app/Http/Controllers/UserPasswordController.php')
        // Cross-feature tests requiring unselected features are pruned
        ->and($plan->prunedFiles)->toContain('tests/Feature/AuditTrails/UserAuditingTest.php')
        ->and($plan->configurationPhases['Administrator Setup'])->toContain('admin@test.local');
});

test('installer planner reports interactive administrator prompt when authorization is enabled without credentials', function (): void {
    $context = new InstallerContext(
        providedAnswers: [],
        answers: [
            'auth_features' => ['login'],
            'optional_modules' => ['authorization'],
        ],
        paths: [],
        selectedModules: ['authorization'],
        selectedAuthFeatures: ['login'],
        hasAuthorization: true,
        adminName: null,
        adminEmail: null,
        adminPassword: null,
        isNonInteractive: false,
        skipNode: false,
        isMockedScript: false,
        isDryRun: true,
    );

    $plan = InstallerPlanner::plan($context, base_path());

    expect($plan->configurationPhases['Administrator Setup'])->toContain('prompt interactively');
});

test('installer planner reports skipped administrator setup in non-interactive mode without credentials', function (): void {
    $context = new InstallerContext(
        providedAnswers: [],
        answers: [
            'auth_features' => ['login'],
            'optional_modules' => ['authorization'],
        ],
        paths: [],
        selectedModules: ['authorization'],
        selectedAuthFeatures: ['login'],
        hasAuthorization: true,
        adminName: null,
        adminEmail: null,
        adminPassword: null,
        isNonInteractive: true,
        skipNode: false,
        isMockedScript: false,
        isDryRun: true,
    );

    $plan = InstallerPlanner::plan($context, base_path());

    expect($plan->configurationPhases['Administrator Setup'])->toContain('Skipped (no administrator credentials provided in non-interactive mode)');
});
