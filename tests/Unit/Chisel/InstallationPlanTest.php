<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallationPlan;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

test('installation plan holds strongly-typed lifecycle properties and converts to array', function (): void {
    $plan = new InstallationPlan(
        explicitAuthFeatures: ['login', 'registration'],
        explicitModules: ['authorization', 'user-management'],
        selectedFeatures: ['login', 'registration', 'authorization', 'user-management'],
        unselectedFeatures: ['settings', 'reporting'],
        dependencyGraph: [
            'login' => [],
            'registration' => ['login'],
            'authorization' => [],
            'user-management' => ['authorization'],
        ],
        requiredBy: [
            'authorization' => ['user-management'],
            'login' => ['registration'],
        ],
        dependencyDerivedFeatures: ['authorization', 'login'],
        prunedFiles: ['resources/js/pages/settings/application/edit.tsx'],
        prunedDirectories: ['resources/js/pages/settings'],
        modifiedFiles: ['app/Models/User.php'],
        retainedComposerPackages: ['spatie/laravel-permission', 'spatie/laravel-data'],
        removedComposerPackages: ['erag/laravel-lang-sync-inertia', 'laravel/chisel'],
        retainedFrontendPackages: [],
        removedFrontendPackages: ['@erag/lang-sync-inertia'],
        migrationCommand: 'php artisan migrate --force',
        configurationPhases: [
            'Authorization Setup' => 'php artisan authorization:setup',
            'Administrator Setup' => 'php artisan admin:setup',
        ],
        postScaffoldCommands: [
            'PHP Code Formatting' => 'vendor/bin/pint --format agent',
        ],
        validationPhases: [
            'Pre-Cleanup Validation' => 'validatePreCleanup',
        ],
        cleanupFiles: ['chisel.php'],
        cleanupDirectories: ['app/Chisel'],
    );

    $array = $plan->toArray();

    expect($array['explicit_auth_features'])->toBe(['login', 'registration'])
        ->and($array['explicit_modules'])->toBe(['authorization', 'user-management'])
        ->and($array['selected_features'])->toBe(['login', 'registration', 'authorization', 'user-management'])
        ->and($array['unselected_features'])->toBe(['settings', 'reporting'])
        ->and($array['dependency_derived_features'])->toBe(['authorization', 'login'])
        ->and($array['pruned_files'])->toBe(['resources/js/pages/settings/application/edit.tsx'])
        ->and($array['pruned_directories'])->toBe(['resources/js/pages/settings'])
        ->and($array['modified_files'])->toBe(['app/Models/User.php'])
        ->and($array['retained_composer_packages'])->toBe(['spatie/laravel-permission', 'spatie/laravel-data'])
        ->and($array['removed_composer_packages'])->toBe(['erag/laravel-lang-sync-inertia', 'laravel/chisel'])
        ->and($array['retained_frontend_packages'])->toBe([])
        ->and($array['removed_frontend_packages'])->toBe(['@erag/lang-sync-inertia'])
        ->and($array['migration_command'])->toBe('php artisan migrate --force')
        ->and($array['configuration_phases'])->toHaveKey('Authorization Setup')
        ->and($array['post_scaffold_commands'])->toHaveKey('PHP Code Formatting')
        ->and($array['validation_phases'])->toHaveKey('Pre-Cleanup Validation')
        ->and($array['cleanup_files'])->toBe(['chisel.php'])
        ->and($array['cleanup_directories'])->toBe(['app/Chisel']);
});

test('installation plan render outputs preview correctly with pruned files and derived dependencies', function (): void {
    $plan = new InstallationPlan(
        explicitAuthFeatures: ['login'],
        explicitModules: ['authorization'],
        selectedFeatures: ['login', 'authorization'],
        unselectedFeatures: ['settings'],
        dependencyGraph: ['login' => [], 'authorization' => []],
        requiredBy: ['authorization' => ['settings']],
        dependencyDerivedFeatures: ['authorization'],
        prunedFiles: ['app/Models/Setting.php'],
        prunedDirectories: ['app/Settings'],
        modifiedFiles: ['routes/web.php'],
        retainedComposerPackages: ['spatie/laravel-permission'],
        removedComposerPackages: ['spatie/laravel-data'],
        retainedFrontendPackages: ['@erag/lang-sync-inertia'],
        removedFrontendPackages: [],
        migrationCommand: 'php artisan migrate:fresh --force',
        configurationPhases: ['Authorization Setup' => 'php artisan authorization:setup'],
        postScaffoldCommands: ['Code Style' => 'pint'],
        validationPhases: ['Pre-Cleanup' => 'validate'],
        cleanupFiles: ['chisel.php'],
        cleanupDirectories: ['app/Chisel'],
    );

    $command = new class extends Command
    {
        protected $name = 'test:dry-run';
    };

    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));

    $plan->render($command);

    $rendered = $output->fetch();

    expect($rendered)->toContain('DRY RUN')
        ->and($rendered)->toContain('Explicit Auth Features')
        ->and($rendered)->toContain('login')
        ->and($rendered)->toContain('Explicit Optional Modules')
        ->and($rendered)->toContain('authorization')
        ->and($rendered)->toContain('Unselected Features (To Prune)')
        ->and($rendered)->toContain('settings')
        ->and($rendered)->toContain('Dependency-Derived Selections')
        ->and($rendered)->toContain('implicitly required by: settings')
        ->and($rendered)->toContain('spatie/laravel-permission')
        ->and($rendered)->toContain('spatie/laravel-data')
        ->and($rendered)->toContain('php artisan migrate:fresh --force')
        ->and($rendered)->toContain('app/Models/Setting.php')
        ->and($rendered)->toContain('app/Settings')
        ->and($rendered)->toContain('0 mutations executed');
});

test('installation plan render outputs fallback labels when collections are empty', function (): void {
    $plan = new InstallationPlan(
        explicitAuthFeatures: [],
        explicitModules: [],
        selectedFeatures: [],
        unselectedFeatures: [],
        dependencyGraph: [],
        requiredBy: [],
        dependencyDerivedFeatures: [],
        prunedFiles: [],
        prunedDirectories: [],
        modifiedFiles: [],
        retainedComposerPackages: [],
        removedComposerPackages: [],
        retainedFrontendPackages: [],
        removedFrontendPackages: [],
        migrationCommand: 'php artisan migrate --force',
        configurationPhases: [],
        postScaffoldCommands: [],
        validationPhases: [],
        cleanupFiles: [],
        cleanupDirectories: [],
    );

    $command = new class extends Command
    {
        protected $name = 'test:dry-run-empty';
    };

    $output = new BufferedOutput;
    $command->setOutput(new OutputStyle(new ArrayInput([]), $output));

    $plan->render($command);

    $rendered = $output->fetch();

    expect($rendered)->toContain('DRY RUN')
        ->and($rendered)->toContain('none')
        ->and($rendered)->toContain('0 mutations executed');
});
