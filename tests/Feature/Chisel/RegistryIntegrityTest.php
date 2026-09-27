<?php

declare(strict_types=1);

test('every composer package declared in chisel-paths.php exists in composer.json', function (): void {
    /** @var array<string, mixed> $paths */
    $paths = require base_path('chisel-paths.php');

    /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    foreach ($paths as $feature => $data) {
        if (! is_array($data) || ! isset($data['composer_package'])) {
            continue;
        }

        $package = (string) $data['composer_package'];
        $exists = isset($composer['require'][$package]) || isset($composer['require-dev'][$package]);

        expect($exists)->toBeTrue("Composer package [{$package}] declared for feature [{$feature}] was not found in composer.json.");
    }
});

test('every frontend package declared in chisel-paths.php exists in package.json', function (): void {
    /** @var array<string, mixed> $paths */
    $paths = require base_path('chisel-paths.php');

    /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $packageJson */
    $packageJson = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);

    foreach ($paths as $feature => $data) {
        if (! is_array($data) || ! isset($data['frontend_package'])) {
            continue;
        }

        $package = (string) $data['frontend_package'];
        $exists = isset($packageJson['dependencies'][$package]) || isset($packageJson['devDependencies'][$package]);

        expect($exists)->toBeTrue("Frontend package [{$package}] declared for feature [{$feature}] was not found in package.json.");
    }
});

test('all dependencies in dependency map refer to valid declared modules', function (): void {
    /** @var array{dependencies: array<string, list<string>>} $paths */
    $paths = require base_path('chisel-paths.php');

    $declaredModules = [
        'authorization',
        'settings',
        'user-management',
        'localization',
        'notifications',
        'audit-trails',
        'reporting',
    ];

    foreach ($paths['dependencies'] as $feature => $required) {
        expect(in_array($feature, $declaredModules, true))->toBeTrue("Dependency source [{$feature}] is not a recognized optional module.");

        foreach ($required as $dependency) {
            expect(in_array($dependency, $declaredModules, true))->toBeTrue("Dependency target [{$dependency}] for [{$feature}] is not a recognized optional module.");
        }
    }
});

test('all cross-feature test files exist and refer to valid features', function (): void {
    /** @var array{cross_feature_tests: list<array{features: list<string>, files: list<string>}>} $paths */
    $paths = require base_path('chisel-paths.php');

    $declaredModules = [
        'authorization',
        'settings',
        'user-management',
        'localization',
        'notifications',
        'audit-trails',
        'reporting',
    ];

    foreach ($paths['cross_feature_tests'] as $entry) {
        foreach ($entry['features'] as $feature) {
            expect(in_array($feature, $declaredModules, true))->toBeTrue("Cross-feature test references unknown feature [{$feature}].");
        }

        foreach ($entry['files'] as $file) {
            expect(file_exists(base_path($file)))->toBeTrue("Cross-feature test file [{$file}] does not exist on disk.");
        }
    }
});

test('all chisel infrastructure cleanup files and directories exist in repository', function (): void {
    /** @var array{chisel: array{files: list<string>, empty_dirs: list<string>}} $paths */
    $paths = require base_path('chisel-paths.php');

    foreach ($paths['chisel']['files'] as $file) {
        expect(file_exists(base_path($file)))->toBeTrue("Chisel file [{$file}] declared for cleanup does not exist on disk.");
    }

    foreach ($paths['chisel']['empty_dirs'] as $dir) {
        expect(is_dir(base_path($dir)))->toBeTrue("Chisel directory [{$dir}] declared for pruning does not exist on disk.");
    }
});
