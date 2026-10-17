<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\Installer\DependencyValidator;

test('dependency validator accepts completely valid combinations', function (array $modules): void {
    $answers = ['optional_modules' => $modules];

    expect(fn () => DependencyValidator::validate($answers))
        ->not->toThrow(Throwable::class);
})->with([
    'empty' => [[]],
    'all modules' => [[
        'authorization',
        'settings',
        'user-management',
        'localization',
        'notifications',
        'audit-trails',
        'reporting',
    ]],
    'authorization only' => [['authorization']],
    'audit-trails with authorization' => [['authorization', 'audit-trails']],
    'reporting with audit, users, and auth' => [['authorization', 'user-management', 'audit-trails', 'reporting']],
]);

test('dependency validator rejects module with missing single dependency', function (): void {
    expect(fn () => DependencyValidator::validate(['optional_modules' => ['settings']]))
        ->toThrow(RuntimeException::class, 'The "settings" feature requires the following feature(s): authorization.');
});

test('dependency validator rejects module with multiple missing dependencies and lists all missing', function (): void {
    expect(fn () => DependencyValidator::validate(['optional_modules' => ['reporting']]))
        ->toThrow(RuntimeException::class, 'The "reporting" feature requires the following feature(s): audit-trails, user-management, authorization.');
});

test('dependency validator rejects cross-feature dependency chain when intermediary is missing', function (): void {
    // reporting has authorization and audit-trails, but user-management is missing
    $answers = [
        'optional_modules' => ['authorization', 'audit-trails', 'reporting'],
    ];

    expect(fn () => DependencyValidator::validate($answers))
        ->toThrow(RuntimeException::class, 'The "reporting" feature requires the following feature(s): user-management.');
});

test('dependency validator works with custom FeatureDefinition instances', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
        ),
        'feature-b' => new FeatureDefinition(
            key: 'feature-b',
            label: 'Feature B',
            dependencies: ['feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validate(['optional_modules' => ['feature-b']], $custom))
        ->toThrow(RuntimeException::class, 'The "feature-b" feature requires the following feature(s): feature-a.');

    expect(fn () => DependencyValidator::validate(['optional_modules' => ['feature-a', 'feature-b']], $custom))
        ->not->toThrow(Throwable::class);
});

test('dependency validator rejects unknown feature keys', function (): void {
    expect(fn () => DependencyValidator::validate(['optional_modules' => ['something-invalid']]))
        ->toThrow(RuntimeException::class, 'Unknown feature(s) specified: something-invalid.');

    expect(fn () => DependencyValidator::validate(['auth_features' => ['login', 'something-invalid']]))
        ->toThrow(RuntimeException::class, 'Unknown feature(s) specified: something-invalid.');
});

test('dependency validator rejects self-dependency in required dependencies', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
            dependencies: ['feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Feature "feature-a" cannot depend on itself.');
});

test('dependency validator rejects self-dependency in optional dependencies', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
            optionalDependencies: ['feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Feature "feature-a" cannot optionally depend on itself.');
});

test('dependency validator rejects unknown required dependency target in definition', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
            dependencies: ['non-existent-dep'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Feature "feature-a" declares unknown dependency "non-existent-dep".');
});

test('dependency validator rejects unknown optional dependency target in definition', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
            optionalDependencies: ['non-existent-optional'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Feature "feature-a" declares unknown optional dependency "non-existent-optional".');
});

test('dependency validator rejects duplicate required dependencies', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
        ),
        'feature-b' => new FeatureDefinition(
            key: 'feature-b',
            label: 'Feature B',
            dependencies: ['feature-a', 'feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Feature "feature-b" declares duplicate dependency "feature-a".');
});

test('dependency validator rejects duplicate optional dependencies', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
        ),
        'feature-b' => new FeatureDefinition(
            key: 'feature-b',
            label: 'Feature B',
            optionalDependencies: ['feature-a', 'feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Feature "feature-b" declares duplicate optional dependency "feature-a".');
});

test('dependency validator rejects overlapping required and optional dependencies', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
        ),
        'feature-b' => new FeatureDefinition(
            key: 'feature-b',
            label: 'Feature B',
            dependencies: ['feature-a'],
            optionalDependencies: ['feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Feature "feature-b" cannot declare "feature-a" as both a required and optional dependency.');
});

test('dependency validator rejects direct circular dependencies', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
            dependencies: ['feature-b'],
        ),
        'feature-b' => new FeatureDefinition(
            key: 'feature-b',
            label: 'Feature B',
            dependencies: ['feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Dependency cycle detected: feature-a -> feature-b -> feature-a.');
});

test('dependency validator rejects indirect circular dependencies across multiple features', function (): void {
    $custom = [
        'feature-a' => new FeatureDefinition(
            key: 'feature-a',
            label: 'Feature A',
            dependencies: ['feature-b'],
        ),
        'feature-b' => new FeatureDefinition(
            key: 'feature-b',
            label: 'Feature B',
            dependencies: ['feature-c'],
        ),
        'feature-c' => new FeatureDefinition(
            key: 'feature-c',
            label: 'Feature C',
            dependencies: ['feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->toThrow(RuntimeException::class, 'Dependency cycle detected: feature-a -> feature-b -> feature-c -> feature-a.');
});

test('dependency validator accepts diamond dependency configurations', function (): void {
    $custom = [
        'feature-base' => new FeatureDefinition(
            key: 'feature-base',
            label: 'Base Feature',
        ),
        'feature-left' => new FeatureDefinition(
            key: 'feature-left',
            label: 'Left Feature',
            dependencies: ['feature-base'],
        ),
        'feature-right' => new FeatureDefinition(
            key: 'feature-right',
            label: 'Right Feature',
            dependencies: ['feature-base'],
        ),
        'feature-top' => new FeatureDefinition(
            key: 'feature-top',
            label: 'Top Feature',
            dependencies: ['feature-left', 'feature-right'],
        ),
    ];

    expect(fn () => DependencyValidator::validateDefinitions($custom))
        ->not->toThrow(Throwable::class);

    // Valid when all selected
    expect(fn () => DependencyValidator::validate([
        'optional_modules' => ['feature-base', 'feature-left', 'feature-right', 'feature-top'],
    ], $custom))->not->toThrow(Throwable::class);

    // Invalid when top is selected but left is missing
    expect(fn () => DependencyValidator::validate([
        'optional_modules' => ['feature-base', 'feature-right', 'feature-top'],
    ], $custom))->toThrow(RuntimeException::class, 'The "feature-top" feature requires the following feature(s): feature-left.');
});

test('optional dependency does not cause validation failure when optional target is absent', function (): void {
    $custom = [
        'feature-core' => new FeatureDefinition(
            key: 'feature-core',
            label: 'Core Feature',
        ),
        'feature-opt' => new FeatureDefinition(
            key: 'feature-opt',
            label: 'Optional Integration Feature',
        ),
        'feature-consumer' => new FeatureDefinition(
            key: 'feature-consumer',
            label: 'Consumer Feature',
            dependencies: ['feature-core'],
            optionalDependencies: ['feature-opt'],
        ),
    ];

    // Consumer enabled, core enabled, optional feature absent -> VALID
    expect(fn () => DependencyValidator::validate([
        'optional_modules' => ['feature-core', 'feature-consumer'],
    ], $custom))->not->toThrow(Throwable::class);

    // Consumer enabled, core enabled, optional feature also present -> VALID
    expect(fn () => DependencyValidator::validate([
        'optional_modules' => ['feature-core', 'feature-opt', 'feature-consumer'],
    ], $custom))->not->toThrow(Throwable::class);

    // Consumer enabled, core absent -> FAILS on required dependency
    expect(fn () => DependencyValidator::validate([
        'optional_modules' => ['feature-consumer'],
    ], $custom))->toThrow(RuntimeException::class, 'The "feature-consumer" feature requires the following feature(s): feature-core.');
});
