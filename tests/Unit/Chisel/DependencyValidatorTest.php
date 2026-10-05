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
        ->toThrow(RuntimeException::class, 'The "settings" module requires the following module(s): authorization.');
});

test('dependency validator rejects module with multiple missing dependencies and lists all missing', function (): void {
    expect(fn () => DependencyValidator::validate(['optional_modules' => ['reporting']]))
        ->toThrow(RuntimeException::class, 'The "reporting" module requires the following module(s): audit-trails, user-management, authorization.');
});

test('dependency validator rejects cross-feature dependency chain when intermediary is missing', function (): void {
    // reporting has authorization and audit-trails, but user-management is missing
    $answers = [
        'optional_modules' => ['authorization', 'audit-trails', 'reporting'],
    ];

    expect(fn () => DependencyValidator::validate($answers))
        ->toThrow(RuntimeException::class, 'The "reporting" module requires the following module(s): user-management.');
});

test('dependency validator works with custom FeatureDefinition instances', function (): void {
    $custom = [
        'feature-b' => new FeatureDefinition(
            key: 'feature-b',
            label: 'Feature B',
            dependencies: ['feature-a'],
        ),
    ];

    expect(fn () => DependencyValidator::validate(['optional_modules' => ['feature-b']], $custom))
        ->toThrow(RuntimeException::class, 'The "feature-b" module requires the following module(s): feature-a.');

    expect(fn () => DependencyValidator::validate(['optional_modules' => ['feature-a', 'feature-b']], $custom))
        ->not->toThrow(Throwable::class);
});
