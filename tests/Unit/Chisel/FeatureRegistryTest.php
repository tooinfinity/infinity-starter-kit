<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureRegistry;
use App\Chisel\Features\AuthFeatures;
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
        ->and($dirs)->toContain('app/Chisel/Features');
});

test('feature registry does not expose cleanup or test metadata methods', function (): void {
    $reflection = new ReflectionClass(FeatureRegistry::class);

    expect($reflection->hasMethod('cleanupFiles'))->toBeFalse()
        ->and($reflection->hasMethod('cleanupDirectories'))->toBeFalse()
        ->and($reflection->hasMethod('crossFeatureTests'))->toBeFalse()
        ->and($reflection->hasMethod('dependencies'))->toBeFalse();
});
