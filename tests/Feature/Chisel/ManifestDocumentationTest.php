<?php

declare(strict_types=1);

use App\Chisel\FeatureRegistry;
use App\Chisel\Installer\GeneratedApplicationManifest;
use App\Chisel\Installer\Preset;
use App\Chisel\ManifestExampleGenerator;

test('committed README manifest example is up to date with authoritative schema', function (): void {
    $readmePath = base_path('README.md');
    expect(file_exists($readmePath))->toBeTrue();

    $content = (string) file_get_contents($readmePath);

    expect(ManifestExampleGenerator::isContentFresh($content))
        ->toBeTrue('The manifest example in README.md is stale. Run `php artisan chisel:docs` to synchronize it.');
});

test('committed README manifest example matches real schema and uses valid feature keys', function (): void {
    $readmePath = base_path('README.md');
    $content = (string) file_get_contents($readmePath);

    $pattern = '/'.preg_quote(ManifestExampleGenerator::START_MARKER, '/').'\s*```json\s*(.*?)\s*```\s*'.preg_quote(ManifestExampleGenerator::END_MARKER, '/').'/s';
    expect(preg_match($pattern, $content, $matches))->toBe(1);

    /** @var array<string, mixed> $manifest */
    $manifest = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

    expect(array_keys($manifest))->toBe([
        'schema_version',
        'starter_kit',
        'preset',
        'features',
        'auth_capabilities',
        'generated_at',
    ]);

    expect($manifest['schema_version'])->toBe(GeneratedApplicationManifest::SCHEMA_VERSION)
        ->and($manifest['preset'])->toBe(Preset::STANDARD);

    expect($manifest['starter_kit'])->toBeArray()
        ->and(array_keys($manifest['starter_kit']))->toBe(['name', 'version', 'source_revision']);

    expect($manifest['features'])->toBeArray()
        ->and(array_keys($manifest['features']))->toBe(['explicit', 'dependency_derived', 'all_selected', 'unselected'])
        ->and(array_keys($manifest['features']['explicit']))->toBe(['auth_features', 'optional_modules']);

    $allRegisteredFeatures = FeatureRegistry::all();
    $allOptionalModules = FeatureRegistry::optionalModules();
    $allAuthFeatures = FeatureRegistry::authFeatures();

    foreach ($manifest['features']['explicit']['auth_features'] as $authFeature) {
        expect($allAuthFeatures)->toHaveKey($authFeature);
    }

    foreach ($manifest['features']['explicit']['optional_modules'] as $module) {
        expect($allOptionalModules)->toHaveKey($module);
    }

    foreach ($manifest['features']['dependency_derived'] as $derived) {
        expect($allRegisteredFeatures)->toHaveKey($derived);
    }

    foreach ($manifest['features']['all_selected'] as $selected) {
        expect($allRegisteredFeatures)->toHaveKey($selected);
    }

    foreach ($manifest['features']['unselected'] as $unselected) {
        expect($allRegisteredFeatures)->toHaveKey($unselected);
    }

    foreach ($manifest['auth_capabilities'] as $capability) {
        expect($allAuthFeatures)->toHaveKey($capability);
    }

    // Verify phantom features from the old invalid example do not exist
    expect($manifest['features']['all_selected'])->not->toContain('teams')
        ->and($manifest['features']['all_selected'])->not->toContain('api')
        ->and($manifest['features']['all_selected'])->not->toContain('health-checks')
        ->and($manifest['features']['unselected'])->not->toContain('billing')
        ->and($manifest)->not->toHaveKey('guidelines')
        ->and($manifest)->not->toHaveKey('options');
});

test('committed README documents schema_version semantics and manifest fields', function (): void {
    $readmePath = base_path('README.md');
    $content = (string) file_get_contents($readmePath);

    expect($content)->toContain('Schema Version Semantics')
        ->and($content)->toContain('schema_version')
        ->and($content)->toContain('starter_kit')
        ->and($content)->toContain('preset')
        ->and($content)->toContain('features')
        ->and($content)->toContain('auth_capabilities')
        ->and($content)->toContain('generated_at')
        ->and($content)->toContain('breaking changes');
});
