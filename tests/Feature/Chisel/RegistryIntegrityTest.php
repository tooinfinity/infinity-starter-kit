<?php

declare(strict_types=1);

use App\Chisel\FeatureRegistry;
use App\Chisel\Features\CrossFeatureTests;

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

test('every exclusive file, marker file, and empty directory across all feature definitions exists on disk', function (): void {
    foreach (FeatureRegistry::all() as $name => $feature) {
        foreach ($feature->exclusiveFiles as $file) {
            expect(file_exists(base_path($file)))->toBeTrue("Exclusive file [{$file}] for feature [{$name}] does not exist on disk.");
        }

        foreach ($feature->markerFiles as $file) {
            expect(file_exists(base_path($file)))->toBeTrue("Marker file [{$file}] for feature [{$name}] does not exist on disk.");
        }

        foreach ($feature->emptyDirectories as $dir) {
            expect(is_dir(base_path($dir)))->toBeTrue("Empty directory [{$dir}] for feature [{$name}] does not exist on disk.");
        }
    }
});

test('cross-feature test files are owned by CrossFeatureTests and not duplicated in individual exclusive files', function (): void {
    $crossFeatureFiles = [];
    foreach (CrossFeatureTests::all() as $entry) {
        foreach ($entry['files'] as $file) {
            $crossFeatureFiles[$file] = true;
        }
    }

    foreach (FeatureRegistry::all() as $name => $feature) {
        foreach ($feature->exclusiveFiles as $file) {
            expect(isset($crossFeatureFiles[$file]))
                ->toBeFalse("Cross-feature test file [{$file}] is erroneously duplicated in exclusiveFiles of [{$name}].");
        }
    }
});

test('all localization and language files are consistently declared and accounted for', function (): void {
    $localization = FeatureRegistry::optionalModules()['localization'];
    $notifications = FeatureRegistry::optionalModules()['notifications'];
    $auditTrails = FeatureRegistry::optionalModules()['audit-trails'];
    $reporting = FeatureRegistry::optionalModules()['reporting'];

    expect($localization->exclusiveFiles)->toContain('lang/en/localization.php')
        ->and($localization->exclusiveFiles)->toContain('lang/fr/common.php')
        ->and($localization->exclusiveFiles)->toContain('lang/ar/common.php');

    expect($notifications->exclusiveFiles)->toContain('lang/en/notifications.php')
        ->and($notifications->exclusiveFiles)->toContain('lang/fr/notifications.php')
        ->and($notifications->exclusiveFiles)->toContain('lang/ar/notifications.php');

    expect($auditTrails->exclusiveFiles)->toContain('lang/en/audit.php')
        ->and($auditTrails->exclusiveFiles)->toContain('lang/fr/audit.php')
        ->and($auditTrails->exclusiveFiles)->toContain('lang/ar/audit.php');

    expect($reporting->exclusiveFiles)->toContain('lang/en/reports.php')
        ->and($reporting->exclusiveFiles)->toContain('lang/fr/reports.php')
        ->and($reporting->exclusiveFiles)->toContain('lang/ar/reports.php');

    // Verify all physical files in lang/ are registered
    $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('lang')));
    foreach ($rdi as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relPath = str_replace(base_path().'/', '', $file->getPathname());
        if ($relPath === 'lang/en/common.php') {
            continue; // Base application fallback
        }

        $isAccounted = in_array($relPath, $localization->exclusiveFiles, true)
            || in_array($relPath, $notifications->exclusiveFiles, true)
            || in_array($relPath, $auditTrails->exclusiveFiles, true)
            || in_array($relPath, $reporting->exclusiveFiles, true);

        expect($isAccounted)->toBeTrue("Translation file [{$relPath}] exists on disk but is not registered in any feature's exclusiveFiles.");
    }
});

test('every composer and frontend package declared across feature definitions exists in manifest', function (): void {
    /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $packageJson */
    $packageJson = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);

    foreach (FeatureRegistry::all() as $name => $feature) {
        foreach ($feature->composerPackages as $pkg) {
            $exists = isset($composer['require'][$pkg]) || isset($composer['require-dev'][$pkg]);
            expect($exists)->toBeTrue("Composer package [{$pkg}] for feature [{$name}] is not present in composer.json.");
        }

        foreach ($feature->frontendPackages as $pkg) {
            $exists = isset($packageJson['dependencies'][$pkg]) || isset($packageJson['devDependencies'][$pkg]);
            expect($exists)->toBeTrue("Frontend package [{$pkg}] for feature [{$name}] is not present in package.json.");
        }
    }
});
