<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureRegistry;
use App\Chisel\Features\AuthFeatures;
use App\Chisel\Features\CrossFeatureTests;
use App\Chisel\Features\OptionalModules;
use App\Chisel\Installer\Cleanup;

test('all expected features are registered in feature registry', function (): void {
    $auth = FeatureRegistry::authFeatures();
    $optional = FeatureRegistry::optionalModules();
    $all = FeatureRegistry::all();

    expect($auth)->toHaveKeys(['registration', 'email-verification', 'two-factor-authentication'])
        ->and($optional)->toHaveKeys([
            'authorization',
            'settings',
            'user-management',
            'localization',
            'notifications',
            'audit-trails',
            'reporting',
        ])
        ->and(count($all))->toBe(count($auth) + count($optional));
});

test('auth features and optional modules are grouped in dedicated classes', function (): void {
    $auth = AuthFeatures::all();
    $optional = OptionalModules::all();

    expect($auth)->toHaveKeys(['registration', 'email-verification', 'two-factor-authentication'])
        ->and($optional)->toHaveKeys([
            'authorization',
            'settings',
            'user-management',
            'localization',
            'notifications',
            'audit-trails',
            'reporting',
        ]);

    foreach ($auth as $definition) {
        expect($definition)->toBeInstanceOf(FeatureDefinition::class);
    }

    foreach ($optional as $definition) {
        expect($definition)->toBeInstanceOf(FeatureDefinition::class);
    }
});

test('feature definition constructor sets all typed properties', function (): void {
    $def = new FeatureDefinition(
        key: 'sample',
        label: 'Sample Feature',
        sectionMarker: 'sample',
        markerFiles: ['sample.php'],
        exclusiveFiles: ['SampleExclusive.php'],
        emptyDirectories: ['sample-dir'],
        composerPackages: ['vendor/sample'],
        frontendPackages: ['sample-npm'],
        dependencies: ['dep1'],
    );

    expect($def->key)->toBe('sample')
        ->and($def->label)->toBe('Sample Feature')
        ->and($def->sectionMarker)->toBe('sample')
        ->and($def->markerFiles)->toBe(['sample.php'])
        ->and($def->exclusiveFiles)->toBe(['SampleExclusive.php'])
        ->and($def->emptyDirectories)->toBe(['sample-dir'])
        ->and($def->composerPackages)->toBe(['vendor/sample'])
        ->and($def->frontendPackages)->toBe(['sample-npm'])
        ->and($def->dependencies)->toBe(['dep1']);
});

test('feature keys in registry are unique and match their definition key', function (): void {
    foreach (FeatureRegistry::all() as $key => $definition) {
        expect($definition)->toBeInstanceOf(FeatureDefinition::class)
            ->and($definition->key)->toBe($key);
    }
});

test('feature registry get returns correct feature definition or throws on unknown key', function (): void {
    $reporting = FeatureRegistry::get('reporting');
    expect($reporting->key)->toBe('reporting')
        ->and($reporting->label)->toBe('Reporting')
        ->and($reporting->sectionMarker)->toBe('reporting');

    expect(fn (): FeatureDefinition => FeatureRegistry::get('unknown-module'))->toThrow(InvalidArgumentException::class);
});

test('feature registry returns correct composer packages and npm packages', function (): void {
    $composerPackages = FeatureRegistry::allComposerPackages();
    expect($composerPackages)->toContain('spatie/laravel-permission')
        ->and($composerPackages)->toContain('erag/laravel-lang-sync-inertia')
        ->and($composerPackages)->toContain('spatie/laravel-data');

    $frontendPackages = FeatureRegistry::allFrontendPackages();
    expect($frontendPackages)->toContain('@erag/lang-sync-inertia');
});

test('registered feature definitions declare expected dependencies', function (): void {
    expect(FeatureRegistry::get('reporting')->dependencies)->toBe(['audit-trails', 'user-management', 'authorization'])
        ->and(FeatureRegistry::get('audit-trails')->dependencies)->toBe(['authorization'])
        ->and(FeatureRegistry::get('user-management')->dependencies)->toBe(['authorization'])
        ->and(FeatureRegistry::get('settings')->dependencies)->toBe(['authorization'])
        ->and(FeatureRegistry::get('authorization')->dependencies)->toBe([])
        ->and(FeatureRegistry::get('localization')->dependencies)->toBe([])
        ->and(FeatureRegistry::get('notifications')->dependencies)->toBe([]);
});

test('toPathsArray produces structure matching chisel-paths specification', function (): void {
    $paths = FeatureRegistry::toPathsArray();

    expect($paths)->toHaveKeys([
        'auth',
        'authorization',
        'settings',
        'user_management',
        'localization',
        'notifications',
        'audit_trails',
        'reporting',
        'data',
        'dependencies',
        'cross_feature_tests',
        'chisel',
    ]);
});

test('cleanup files and directories are owned by Cleanup class', function (): void {
    $files = Cleanup::files();
    $dirs = Cleanup::directories();

    expect($files)->toContain('app/Chisel/Features/AuthFeatures.php')
        ->and($files)->toContain('app/Chisel/Features/OptionalModules.php')
        ->and($files)->toContain('app/Chisel/Features/CrossFeatureTests.php')
        ->and($dirs)->toContain('app/Chisel/Features');
});

test('feature registry does not expose cleanup or test metadata methods', function (): void {
    $reflection = new ReflectionClass(FeatureRegistry::class);

    expect($reflection->hasMethod('cleanupFiles'))->toBeFalse()
        ->and($reflection->hasMethod('cleanupDirectories'))->toBeFalse()
        ->and($reflection->hasMethod('crossFeatureTests'))->toBeFalse()
        ->and($reflection->hasMethod('dependencies'))->toBeFalse();
});

test('toPathsArray composer packages match feature definition composer packages', function (): void {
    $paths = FeatureRegistry::toPathsArray();
    $optionalModules = FeatureRegistry::optionalModules();

    $featureToPathsKey = [
        'authorization' => 'authorization',
        'localization' => 'localization',
    ];

    foreach ($featureToPathsKey as $featureKey => $pathsKey) {
        $definition = $optionalModules[$featureKey];
        $pathData = $paths[$pathsKey];

        if (isset($definition->composerPackages[0])) {
            expect($pathData['composer_package'] ?? null)
                ->toBe($definition->composerPackages[0], "Composer package for [{$featureKey}] diverges between FeatureDefinition and toPathsArray().");
        }
    }

    foreach ($optionalModules as $key => $definition) {
        if ($definition->composerPackages === []) {
            $pathsKey = str_replace('-', '_', $key);
            if (isset($paths[$pathsKey])) {
                expect(isset($paths[$pathsKey]['composer_package']))
                    ->toBeFalse("Feature [{$key}] has no composer packages in definition but toPathsArray declares one.");
            }
        }
    }
});

test('toPathsArray frontend packages match feature definition frontend packages', function (): void {
    $paths = FeatureRegistry::toPathsArray();
    $optionalModules = FeatureRegistry::optionalModules();

    foreach ($optionalModules as $key => $definition) {
        $pathsKey = str_replace('-', '_', $key);
        if (! isset($paths[$pathsKey]) || ! is_array($paths[$pathsKey])) {
            continue;
        }

        $pathData = $paths[$pathsKey];

        if (isset($definition->frontendPackages[0])) {
            expect($pathData['frontend_package'] ?? null)
                ->toBe($definition->frontendPackages[0], "Frontend package for [{$key}] diverges between FeatureDefinition and toPathsArray().");
        } else {
            expect(isset($pathData['frontend_package']))
                ->toBeFalse("Feature [{$key}] has no frontend packages in definition but toPathsArray declares one.");
        }
    }
});

test('toPathsArray empty directories match feature definition empty directories for optional modules', function (): void {
    $paths = FeatureRegistry::toPathsArray();
    $optionalModules = FeatureRegistry::optionalModules();

    foreach ($optionalModules as $key => $definition) {
        $pathsKey = str_replace('-', '_', $key);
        if (! isset($paths[$pathsKey]) || ! is_array($paths[$pathsKey])) {
            continue;
        }

        $pathData = $paths[$pathsKey];

        if ($definition->emptyDirectories !== []) {
            expect($pathData['empty_dirs'] ?? null)
                ->toBe($definition->emptyDirectories, "Empty directories for [{$key}] diverge between FeatureDefinition and toPathsArray().");
        }
    }
});

test('allComposerPackages includes every package from all feature definitions', function (): void {
    $allPackages = FeatureRegistry::allComposerPackages();

    foreach (FeatureRegistry::all() as $definition) {
        foreach ($definition->composerPackages as $pkg) {
            expect($allPackages)->toContain($pkg);
        }
    }
});

test('cross-feature test definitions are owned by production CrossFeatureTests class', function (): void {
    $crossTests = CrossFeatureTests::all();
    expect($crossTests)->not->toBeEmpty();

    foreach ($crossTests as $entry) {
        expect($entry)->toHaveKeys(['features', 'files'])
            ->and($entry['features'])->toBeArray()
            ->and($entry['files'])->toBeArray();
    }

    expect(Tests\Support\CrossFeatureTests::all())->toBe($crossTests);
});

test('FeatureRegistry toPathsArray cross_feature_tests is sourced from production CrossFeatureTests', function (): void {
    $paths = FeatureRegistry::toPathsArray();
    expect($paths['cross_feature_tests'])->toBe(CrossFeatureTests::all());
});

test('FeatureRegistry does not import or depend on Tests namespace', function (): void {
    $file = (string) file_get_contents(app_path('Chisel/FeatureRegistry.php'));
    expect($file)->not->toContain('Tests\\');
});

test('toPathsArray extra_lang_files matches localization non-default language exclusive files', function (): void {
    $paths = FeatureRegistry::toPathsArray();
    $localization = FeatureRegistry::optionalModules()['localization'];

    $expected = array_values(array_filter(
        $localization->exclusiveFiles,
        fn (string $file): bool => str_starts_with($file, 'lang/fr/') || str_starts_with($file, 'lang/ar/'),
    ));

    expect($paths['localization']['extra_lang_files'])->toBe($expected)
        ->and($paths['localization']['extra_lang_files'])->toContain('lang/fr/localization.php')
        ->and($paths['localization']['extra_lang_files'])->toContain('lang/ar/localization.php')
        ->and($paths['localization']['extra_lang_files'])->not->toContain('lang/en/localization.php');
});

test('toPathsArray dependencies match declared dependencies in FeatureDefinitions', function (): void {
    $paths = FeatureRegistry::toPathsArray();
    $expected = [];
    foreach (FeatureRegistry::optionalModules() as $key => $feature) {
        if ($feature->dependencies !== []) {
            $expected[$key] = $feature->dependencies;
        }
    }

    expect(array_keys($paths['dependencies']))->toHaveCount(count($expected));
    foreach ($expected as $key => $deps) {
        expect($paths['dependencies'][$key])->toBe($deps);
    }
});
