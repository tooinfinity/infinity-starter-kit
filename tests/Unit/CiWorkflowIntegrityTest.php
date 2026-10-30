<?php

declare(strict_types=1);

test('composer manifest defines all required test commands and scripts', function (): void {
    /** @var array{scripts?: array<string, mixed>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    $scripts = $composer['scripts'] ?? [];

    expect($scripts)->toHaveKey('test:lint')
        ->and($scripts)->toHaveKey('test:types')
        ->and($scripts)->toHaveKey('test:type-coverage')
        ->and($scripts)->toHaveKey('test:unit')
        ->and($scripts)->toHaveKey('test:database')
        ->and($scripts)->toHaveKey('test:browser')
        ->and($scripts)->toHaveKey('test:coverage')
        ->and($scripts)->toHaveKey('test:quick')
        ->and($scripts)->toHaveKey('test:installer')
        ->and($scripts)->toHaveKey('test:quality')
        ->and($scripts)->toHaveKey('test');

    expect($scripts['test'])->toBe([
        '@test:lint',
        '@test:type-coverage',
        '@test:types',
        '@test:coverage',
    ]);

    expect($scripts['test:quality'])->toBe([
        '@test:lint',
        '@test:types',
        '@test:type-coverage',
        '@test:unit',
    ]);
});

test('tests workflow defines decoupled quality, database, browser, and installer jobs', function (): void {
    $workflowPath = base_path('.github/workflows/tests.yml');
    expect(file_exists($workflowPath))->toBeTrue();

    $content = (string) file_get_contents($workflowPath);

    // Verify all 4 required jobs are present
    expect($content)->toContain('  quality:')
        ->and($content)->toContain('  database:')
        ->and($content)->toContain('  browser:')
        ->and($content)->toContain('  installer-e2e:');

    // Quality job runs database-independent checks and coverage
    expect($content)->toContain('composer test:lint')
        ->and($content)->toContain('composer test:types')
        ->and($content)->toContain('composer test:type-coverage')
        ->and($content)->toContain('composer test:unit')
        ->and($content)->toContain('composer test:coverage');

    // Database matrix covers sqlite, mysql, pgsql with database tests and builds assets for Inertia views
    expect($content)->toContain('db: [sqlite, mysql, pgsql]')
        ->and($content)->toContain('composer test:database');
    $databaseSection = mb_strstr($content, '  database:');
    expect($databaseSection)->toContain('bun run build');

    // Browser tests run browser suite
    expect($content)->toContain('composer test:browser');

    // Installer E2E runs installer suite
    expect($content)->toContain('composer test:installer');
});

test('phpunit configuration registers browser, unit, and feature testsuites', function (): void {
    $phpunitPath = base_path('phpunit.xml');
    expect(file_exists($phpunitPath))->toBeTrue();

    $content = (string) file_get_contents($phpunitPath);

    expect($content)->toContain('<testsuite name="Browser">')
        ->and($content)->toContain('<directory>tests/Browser</directory>')
        ->and($content)->toContain('<testsuite name="Unit">')
        ->and($content)->toContain('<directory>tests/Unit</directory>')
        ->and($content)->toContain('<testsuite name="Feature">')
        ->and($content)->toContain('<directory>tests/Feature</directory>');
});
