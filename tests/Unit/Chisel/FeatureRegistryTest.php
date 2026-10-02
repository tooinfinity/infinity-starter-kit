<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureRegistry;

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

    expect(fn () => FeatureRegistry::get('unknown-module'))->toThrow(InvalidArgumentException::class);
});

test('feature registry returns correct composer packages and npm packages', function (): void {
    $composerPackages = FeatureRegistry::allComposerPackages();
    expect($composerPackages)->toContain('spatie/laravel-permission')
        ->and($composerPackages)->toContain('erag/laravel-lang-sync-inertia')
        ->and($composerPackages)->toContain('spatie/laravel-data');

    $frontendPackages = FeatureRegistry::allFrontendPackages();
    expect($frontendPackages)->toContain('@erag/lang-sync-inertia');
});

test('feature registry dependencies match expected dependency graph', function (): void {
    $deps = FeatureRegistry::dependencies();

    expect($deps)->toBe([
        'reporting' => ['audit-trails', 'user-management', 'authorization'],
        'audit-trails' => ['authorization'],
        'user-management' => ['authorization'],
        'settings' => ['authorization'],
    ]);
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
