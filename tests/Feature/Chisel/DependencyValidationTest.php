<?php

declare(strict_types=1);

require_once __DIR__.'/../../../chisel.php';

beforeEach(function (): void {
    /** @var array{dependencies: array<string, list<string>>} $paths */
    $paths = require base_path('chisel-paths.php');
    $this->dependencies = $paths['dependencies'];
});

test('dependency map is properly defined and has expected structure', function (): void {
    expect($this->dependencies)->toBeArray()
        ->and($this->dependencies)->toHaveKeys([
            'reporting',
            'audit-trails',
            'user-management',
            'settings',
        ]);

    expect($this->dependencies['reporting'])->toContain('audit-trails', 'user-management', 'authorization')
        ->and($this->dependencies['audit-trails'])->toContain('authorization')
        ->and($this->dependencies['user-management'])->toContain('authorization')
        ->and($this->dependencies['settings'])->toContain('authorization');
});

test('chiselValidateDependencies rejects missing required dependencies', function (array $modules, string $expectedMessage): void {
    $answers = [
        'optional_modules' => $modules,
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, $expectedMessage);
})->with([
    'reporting without audit-trails' => [
        ['reporting', 'user-management', 'authorization'],
        'The "reporting" feature requires the following feature(s): audit-trails.',
    ],
    'reporting without user-management' => [
        ['reporting', 'audit-trails', 'authorization'],
        'The "reporting" feature requires the following feature(s): user-management.',
    ],
    'reporting without authorization' => [
        ['reporting', 'audit-trails', 'user-management'],
        'The "reporting" feature requires the following feature(s): authorization.',
    ],
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
        'reporting',
    ]],
    'authorization only' => [['authorization']],
    'localization only' => [['localization']],
    'notifications only' => [['notifications']],
    'localization and notifications' => [['localization', 'notifications']],
    'authorization and settings' => [['authorization', 'settings']],
    'authorization and user-management' => [['authorization', 'user-management']],
    'authorization and audit-trails' => [['authorization', 'audit-trails']],
    'authorization, audit-trails, user-management and reporting' => [[
        'authorization',
        'audit-trails',
        'user-management',
        'reporting',
    ]],
]);
