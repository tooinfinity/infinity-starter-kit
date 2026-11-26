<?php

declare(strict_types=1);

use App\Chisel\FeatureRegistry;

require_once __DIR__.'/../../../chisel.php';

beforeEach(function (): void {
    /** @var array{dependencies: array<string, list<string>>} $paths */
    $paths = require base_path('chisel-paths.php');
    $this->dependencies = $paths['dependencies'];
});

test('dependency map is properly defined and has expected structure', function (): void {
    expect($this->dependencies)->toBeArray()
        ->and($this->dependencies)->toHaveKeys([
            'audit-trails',
            'user-management',
            'settings',
        ]);

    expect($this->dependencies['audit-trails'])->toContain('authorization')
        ->and($this->dependencies['user-management'])->toContain('authorization')
        ->and($this->dependencies['settings'])->toContain('authorization');
});

test('audit-trails declares spatie/laravel-activitylog as exclusive package dependency in feature definition', function (): void {
    $feature = FeatureRegistry::get('audit-trails');

    expect($feature->composerPackages)->toBe(['spatie/laravel-activitylog'])
        ->and($feature->dependencies)->toBe(['authorization']);
});

test('chiselValidateDependencies rejects missing required dependencies', function (array $modules, string $expectedMessage): void {
    $answers = [
        'optional_modules' => $modules,
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, $expectedMessage);
})->with([
    'audit-trails without authorization' => [
        ['audit-trails'],
        'The "audit-trails" feature requires the following feature(s): authorization.',
    ],
    'user-management without authorization' => [
        ['user-management'],
        'The "user-management" feature requires the following feature(s): authorization.',
    ],
    'settings without authorization' => [
        ['settings'],
        'The "settings" feature requires the following feature(s): authorization.',
    ],
]);

test('chiselValidateDependencies accepts valid combinations', function (array $modules): void {
    $answers = [
        'optional_modules' => $modules,
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->not->toThrow(Throwable::class);
})->with([
    'all modules empty' => [[]],
    'all modules enabled' => [[
        'authorization',
        'settings',
        'user-management',
        'localization',
        'notifications',
        'audit-trails',
    ]],
    'authorization only' => [['authorization']],
    'localization only' => [['localization']],
    'notifications only' => [['notifications']],
    'localization and notifications' => [['localization', 'notifications']],
    'authorization and settings' => [['authorization', 'settings']],
    'authorization and user-management' => [['authorization', 'user-management']],
    'authorization and audit-trails' => [['authorization', 'audit-trails']],
    'authorization, audit-trails, and user-management' => [[
        'authorization',
        'audit-trails',
        'user-management',
    ]],
]);
