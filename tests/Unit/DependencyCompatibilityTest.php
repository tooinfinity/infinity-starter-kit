<?php

declare(strict_types=1);

test('composer manifest defines supported PHP and Laravel version constraints', function (): void {
    /** @var array{require?: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    $require = $composer['require'] ?? [];

    expect($require)->toHaveKey('php')
        ->and($require['php'])->toBe('^8.5.0')
        ->and($require)->toHaveKey('laravel/framework')
        ->and($require['laravel/framework'])->toBe('^13.32.0');
});

test('composer manifest defines critical framework integration packages', function (): void {
    /** @var array{require?: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    $require = $composer['require'] ?? [];

    // Core packages are always present across starter kit template and generated applications
    expect($require)->toHaveKey('laravel/fortify')
        ->and($require)->toHaveKey('laravel/wayfinder')
        ->and($require)->toHaveKey('inertiajs/inertia-laravel');

    // In the template repository prior to scaffolding, verify chisel and permission
    if (file_exists(base_path('chisel.php'))) {
        expect($require)->toHaveKey('laravel/chisel')
            ->and($require)->toHaveKey('spatie/laravel-permission');
    } elseif (file_exists(base_path('app/Enums/Permission.php'))) {
        expect($require)->toHaveKey('spatie/laravel-permission');
    }
});

test('frontend package manifest defines supported toolchain and library constraints', function (): void {
    /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $package */
    $package = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);

    $dependencies = $package['dependencies'] ?? [];
    $devDependencies = $package['devDependencies'] ?? [];

    expect($dependencies)->toHaveKey('react')
        ->and($dependencies['react'])->toBe('^19.3.0')
        ->and($dependencies)->toHaveKey('react-dom')
        ->and($dependencies['react-dom'])->toBe('^19.3.0')
        ->and($dependencies)->toHaveKey('@inertiajs/react')
        ->and($dependencies['@inertiajs/react'])->toBe('^3.7.1');

    expect($devDependencies)->toHaveKey('@inertiajs/vite')
        ->and($devDependencies)->toHaveKey('@laravel/vite-plugin-wayfinder')
        ->and($devDependencies)->toHaveKey('typescript')
        ->and($devDependencies)->toHaveKey('tailwindcss')
        ->and($devDependencies)->toHaveKey('vite-plus');

    expect($package['scripts'] ?? [])->toHaveKey('test:peers');
});

test('backend and frontend lockfiles exist and enforce reproducibility', function (): void {
    $composerLockPath = base_path('composer.lock');
    $bunLockPath = base_path('bun.lock');

    expect(file_exists($composerLockPath))->toBeTrue('composer.lock must exist for reproducible backend resolution.')
        ->and(filesize($composerLockPath))->toBeGreaterThan(0);

    expect(file_exists($bunLockPath))->toBeTrue('bun.lock must exist for reproducible frontend resolution.')
        ->and(filesize($bunLockPath))->toBeGreaterThan(0);

    /** @var array{platform?: array{php?: string}} $composerLock */
    $composerLock = json_decode((string) file_get_contents($composerLockPath), true, 512, JSON_THROW_ON_ERROR);

    expect($composerLock['platform']['php'] ?? null)->toBe('^8.5.0');
});

test('tests workflow defines clean dependency compatibility guard job', function (): void {
    $workflowPath = base_path('.github/workflows/tests.yml');
    if (! file_exists($workflowPath)) {
        expect(true)->toBeTrue();

        return;
    }

    $content = (string) file_get_contents($workflowPath);

    expect($content)->toContain('  dependencies:')
        ->and($content)->toContain('composer validate --strict --check-lock')
        ->and($content)->toContain('composer check-platform-reqs --lock')
        ->and($content)->toContain('composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress')
        ->and($content)->toContain('bun install --frozen-lockfile')
        ->and($content)->toContain('bun run test:peers')
        ->and($content)->toContain('bun pm ls')
        ->and($content)->toContain('php artisan package:discover')
        ->and($content)->toContain('php artisan wayfinder:generate')
        ->and($content)->toContain('bun run build')
        ->and($content)->toContain('php artisan about');

    // Ensure the dependencies job does not restore cached vendor or bun directories
    $dependenciesSection = mb_strstr($content, '  dependencies:');
    $databaseSection = mb_strstr((string) $dependenciesSection, '  database:');
    $dependenciesJobContent = mb_substr((string) $dependenciesSection, 0, mb_strlen((string) $dependenciesSection) - mb_strlen((string) $databaseSection));

    expect($dependenciesJobContent)->not->toContain('actions/cache@v4');
});
