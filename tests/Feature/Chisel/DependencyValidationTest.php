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

test('chiselValidateDependencies rejects reporting without audit-trails', function (): void {
    $answers = [
        'optional_modules' => ['reporting', 'user-management', 'authorization'],
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, 'The "reporting" module requires the following module(s): audit-trails.');
});

test('chiselValidateDependencies rejects reporting without user-management', function (): void {
    $answers = [
        'optional_modules' => ['reporting', 'audit-trails', 'authorization'],
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, 'The "reporting" module requires the following module(s): user-management.');
});

test('chiselValidateDependencies rejects reporting without authorization', function (): void {
    $answers = [
        'optional_modules' => ['reporting', 'audit-trails', 'user-management'],
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, 'The "reporting" module requires the following module(s): authorization.');
});

test('chiselValidateDependencies rejects audit-trails without authorization', function (): void {
    $answers = [
        'optional_modules' => ['audit-trails'],
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, 'The "audit-trails" module requires the following module(s): authorization.');
});

test('chiselValidateDependencies rejects user-management without authorization', function (): void {
    $answers = [
        'optional_modules' => ['user-management'],
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, 'The "user-management" module requires the following module(s): authorization.');
});

test('chiselValidateDependencies rejects settings without authorization', function (): void {
    $answers = [
        'optional_modules' => ['settings'],
    ];

    expect(fn () => chiselValidateDependencies($answers, $this->dependencies))
        ->toThrow(RuntimeException::class, 'The "settings" module requires the following module(s): authorization.');
});

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
