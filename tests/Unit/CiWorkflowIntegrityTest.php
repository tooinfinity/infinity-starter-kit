<?php

declare(strict_types=1);

use App\Chisel\Installer\Cleanup;

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
        ->and($scripts)->toHaveKey('test:dependencies')
        ->and($scripts)->toHaveKey('test:quality')
        ->and($scripts)->toHaveKey('test');

    expect($scripts['test:dependencies'])->toBe([
        'composer validate --strict --check-lock',
        'composer check-platform-reqs --lock',
        'bun install --frozen-lockfile --dry-run',
        'bun run test:peers',
    ]);

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

test('tests workflow defines decoupled application quality, dependencies, database, and browser jobs', function (): void {
    $workflowPath = base_path('.github/workflows/tests.yml');
    expect(file_exists($workflowPath))->toBeTrue();

    $content = (string) file_get_contents($workflowPath);

    // Verify application jobs are present and installer jobs are decoupled into installer.yml
    expect($content)->toContain('  quality:')
        ->and($content)->toContain('  dependencies:')
        ->and($content)->toContain('  database:')
        ->and($content)->toContain('  browser:')
        ->and($content)->not->toContain('  installer-e2e:')
        ->and($content)->not->toContain('  installer-smoke:');

    // Quality job runs database-independent checks and coverage without installer docs check
    expect($content)->toContain('composer test:lint')
        ->and($content)->toContain('composer test:types')
        ->and($content)->toContain('composer test:type-coverage')
        ->and($content)->toContain('composer test:unit')
        ->and($content)->toContain('composer test:coverage')
        ->and($content)->not->toContain('php artisan chisel:docs');

    // Dependencies job acts as clean compatibility guard without cache
    $dependenciesSection = mb_strstr($content, '  dependencies:');
    $databaseSection = mb_strstr((string) $dependenciesSection, '  database:');
    $dependenciesJob = mb_substr((string) $dependenciesSection, 0, mb_strlen((string) $dependenciesSection) - mb_strlen((string) $databaseSection));

    expect($dependenciesJob)->toContain('composer validate --strict --check-lock')
        ->and($dependenciesJob)->toContain('composer check-platform-reqs --lock')
        ->and($dependenciesJob)->toContain('composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress')
        ->and($dependenciesJob)->toContain('bun install --frozen-lockfile')
        ->and($dependenciesJob)->toContain('bun run test:peers')
        ->and($dependenciesJob)->toContain('bun pm ls')
        ->and($dependenciesJob)->toContain('php artisan package:discover')
        ->and($dependenciesJob)->toContain('php artisan wayfinder:generate')
        ->and($dependenciesJob)->toContain('bun run build')
        ->and($dependenciesJob)->toContain('php artisan about')
        ->and($dependenciesJob)->not->toContain('actions/cache@v4');

    // Database matrix covers sqlite, mysql, pgsql with database tests and builds assets for Inertia views
    expect($content)->toContain('db: [sqlite, mysql, pgsql]')
        ->and($content)->toContain('composer test:database');
    $databaseSection = mb_strstr($content, '  database:');
    expect($databaseSection)->toContain('bun run build');

    // Browser tests run browser suite
    expect($content)->toContain('composer test:browser');
});

test('cleanup files list registers starter kit workflows, tests, and guidelines for removal in new projects', function (): void {
    $cleanupFiles = Cleanup::files();

    expect($cleanupFiles)->toContain('.github/workflows/combos.yml')
        ->and($cleanupFiles)->toContain('scripts/verify-combo.sh')
        ->and($cleanupFiles)->toContain('scripts/combos.php')
        ->and($cleanupFiles)->toContain('tests/Unit/CombosPairwiseTest.php')
        ->and($cleanupFiles)->toContain('tests/Unit/CiWorkflowIntegrityTest.php')
        ->and($cleanupFiles)->toContain('.ai/guidelines/app.chisel.blade.php');
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

test('guidelines and agent instructions reference only valid composer and bun scripts', function (): void {
    /** @var array{scripts?: array<string, mixed>} $packageJson */
    $packageJson = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
    $packageScripts = array_keys($packageJson['scripts'] ?? []);

    /** @var array{scripts?: array<string, mixed>} $composerJson */
    $composerJson = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
    $composerScripts = array_keys($composerJson['scripts'] ?? []);

    $builtInComposerCommands = [
        'install', 'update', 'require', 'remove', 'dump-autoload', 'dumpautoload',
        'exec', 'run-script', 'run', 'show', 'validate', 'check-platform-reqs',
        'fund', 'diagnose', 'help', 'list', 'clear-cache', 'create-project', 'config', 'init',
    ];

    $guidelineFiles = array_merge(
        glob(base_path('.ai/guidelines/*.blade.php')) ?: [],
        [base_path('AGENTS.md'), base_path('CLAUDE.md'), base_path('GEMINI.md')],
    );

    expect($guidelineFiles)->not->toBeEmpty();

    foreach ($guidelineFiles as $file) {
        expect(file_exists($file))->toBeTrue("Expected [{$file}] to exist.");
        $content = (string) file_get_contents($file);

        if (preg_match_all('/bun run ([a-zA-Z0-9:_-]+)/', $content, $bunMatches)) {
            foreach ($bunMatches[1] as $script) {
                expect(in_array($script, $packageScripts, true))->toBeTrue(
                    "File [{$file}] references missing bun run script [{$script}]."
                );
            }
        }

        if (preg_match_all('/composer (?:run )?([a-zA-Z0-9:_-]+)/', $content, $composerMatches)) {
            foreach ($composerMatches[1] as $script) {
                $isValid = in_array($script, $composerScripts, true) || in_array($script, $builtInComposerCommands, true);
                expect($isValid)->toBeTrue(
                    "File [{$file}] references missing composer script [{$script}]."
                );
            }
        }
    }
});
