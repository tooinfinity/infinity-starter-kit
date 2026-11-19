<?php

declare(strict_types=1);

use App\Chisel\Installer\Cleanup;
use Symfony\Component\Yaml\Yaml;

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

    // Parse quality job steps to verify bun run test:unit is present and enabled
    $qualitySection = mb_strstr($content, '  quality:');
    $dependenciesSection = mb_strstr((string) $qualitySection, '  dependencies:');
    $qualityJob = mb_substr((string) $qualitySection, 0, mb_strlen((string) $qualitySection) - mb_strlen((string) $dependenciesSection));

    $parseWorkflowSteps = function (string $jobContent): array {
        $stepBlocks = preg_split('/^[ ]{6}-[ ]+/m', $jobContent) ?: [];
        array_shift($stepBlocks);

        return array_map(function (string $block): array {
            $properties = [];
            if (preg_match_all('/^[ ]*(?<key>[a-zA-Z0-9_-]+):[ ]*(?<val>[^\n]+)/m', $block, $propMatches, PREG_SET_ORDER)) {
                foreach ($propMatches as $prop) {
                    $properties[$prop['key']] = mb_trim($prop['val'], " '\"");
                }
            }

            return ['name' => $properties['name'] ?? null, 'properties' => $properties];
        }, $stepBlocks);
    };

    $qualitySteps = $parseWorkflowSteps($qualityJob);
    $frontendTestStep = collect($qualitySteps)->first(fn (array $step): bool => ($step['properties']['run'] ?? null) === 'bun run test:unit');
    expect($frontendTestStep)->not->toBeNull()
        ->and($frontendTestStep['properties']['if'] ?? null)->not->toBe('false');

    // Regression: step properties across intervening blank lines (e.g. if: false) are not ignored
    $sampleDisabledJob = <<<'YAML'
      quality:
        steps:
          - name: Run frontend unit tests
            run: bun run test:unit

            if: false
    YAML;
    $parsedSampleSteps = $parseWorkflowSteps($sampleDisabledJob);
    $sampleDisabledStep = collect($parsedSampleSteps)->first(fn (array $step): bool => ($step['properties']['run'] ?? null) === 'bun run test:unit');
    expect($sampleDisabledStep)->not->toBeNull()
        ->and($sampleDisabledStep['properties']['if'] ?? null)->toBe('false');

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

test('security workflow audits locked dev dependencies and fails on high-severity bun advisories', function (): void {
    $workflowPath = base_path('.github/workflows/security.yml');
    expect(file_exists($workflowPath))->toBeTrue();

    $workflow = Yaml::parse((string) file_get_contents($workflowPath));
    expect($workflow)->toBeArray();

    // Scheduled workflow retains least-privilege permissions: read only, no write
    $permissions = $workflow['permissions'] ?? [];
    expect($permissions)->toBeArray()
        ->and($permissions)->toHaveKey('contents')
        ->and($permissions['contents'])->toBe('read')
        ->and($permissions)->not->toHaveKey('write');

    // Every action reference is pinned to a full 40-character commit SHA
    $actionRefs = collect($workflow['jobs'] ?? [])
        ->flatMap(fn (array $job): array => collect($job['steps'] ?? [])
            ->filter(fn (array $step): bool => isset($step['uses']))
            ->map(fn (array $step): string => $step['uses'])
            ->all())
        ->filter(fn (string $ref): bool => str_contains($ref, '@'))
        ->all();

    expect($actionRefs)->not->toBeEmpty();
    foreach ($actionRefs as $ref) {
        $sha = explode('@', $ref)[1] ?? '';
        expect($sha)->toMatch('/^[0-9a-f]{40}$/');
    }

    // Composer audit evaluates the complete locked graph including dev dependencies
    $findRun = fn (string $command): ?string => collect($workflow['jobs'] ?? [])
        ->flatMap(fn (array $job): array => collect($job['steps'] ?? [])
            ->filter(fn (array $step): bool => isset($step['run']) && str_starts_with($step['run'], $command))
            ->map(fn (array $step): string => $step['run'])
            ->all())
        ->first();

    $composerAuditCommand = $findRun('composer audit');
    expect($composerAuditCommand)->not->toBeNull();
    $tokens = preg_split('/\s+/', $composerAuditCommand);
    expect($tokens)->toContain('--locked')
        ->and($tokens)->not->toContain('--no-dev');

    // Bun audit fails on high-severity advisories (or a more sensitive threshold)
    $bunAuditCommand = $findRun('bun audit');
    expect($bunAuditCommand)->not->toBeNull();
    expect($bunAuditCommand)->toMatch('/--audit-level=(high|moderate|low|info)/');
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

        if (preg_match_all('/bun[^\S\r\n]+run[^\S\r\n]+([^\s`"\'\(\)\[\],;&|]+)/', $content, $bunMatches)) {
            foreach ($bunMatches[1] as $script) {
                expect(in_array($script, $packageScripts, true))->toBeTrue(
                    "File [{$file}] references missing bun run script [{$script}]."
                );
            }
        }

        if (preg_match_all('/composer[^\S\r\n]+([^\s`"\'\(\)\[\],;&|]+)([^\r\n`"\'\)&|;]*)/', $content, $composerMatches, PREG_SET_ORDER)) {
            foreach ($composerMatches as $match) {
                $command = $match[1];
                $rest = mb_trim($match[2]);

                if (in_array($command, ['run', 'run-script'], true)) {
                    $tokens = preg_split('/[^\S\r\n]+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $script = null;
                    foreach ($tokens as $token) {
                        $cleanedToken = mb_trim($token, "`,;()[]\"'");
                        if (! str_starts_with($cleanedToken, '-')) {
                            $script = $cleanedToken;
                            break;
                        }
                    }

                    expect($script !== null && in_array($script, $composerScripts, true))->toBeTrue(
                        "File [{$file}] references missing composer script [".($script ?? '')."] via explicit {$command}."
                    );
                } else {
                    $isValid = in_array($command, $composerScripts, true) || in_array($command, $builtInComposerCommands, true);
                    expect($isValid)->toBeTrue(
                        "File [{$file}] references missing composer command or script [{$command}]."
                    );
                }
            }
        }
    }
});

test('guidelines script integrity validation rejects invalid suffixes, missing scripts, and invalid run-script invocations', function (): void {
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

    $validate = function (string $content) use ($packageScripts, $composerScripts, $builtInComposerCommands): array {
        $errors = [];

        if (preg_match_all('/bun[^\S\r\n]+run[^\S\r\n]+([^\s`"\'\(\)\[\],;&|]+)/', $content, $bunMatches)) {
            foreach ($bunMatches[1] as $script) {
                if (! in_array($script, $packageScripts, true)) {
                    $errors[] = "missing bun script: {$script}";
                }
            }
        }

        if (preg_match_all('/composer[^\S\r\n]+([^\s`"\'\(\)\[\],;&|]+)([^\r\n`"\'\)&|;]*)/', $content, $composerMatches, PREG_SET_ORDER)) {
            foreach ($composerMatches as $match) {
                $command = $match[1];
                $rest = mb_trim($match[2]);

                if (in_array($command, ['run', 'run-script'], true)) {
                    $tokens = preg_split('/[^\S\r\n]+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    $script = null;
                    foreach ($tokens as $token) {
                        $cleanedToken = mb_trim($token, "`,;()[]\"'");
                        if (! str_starts_with($cleanedToken, '-')) {
                            $script = $cleanedToken;
                            break;
                        }
                    }

                    if ($script === null || ! in_array($script, $composerScripts, true)) {
                        $errors[] = 'missing explicit composer script: '.($script ?? '');
                    }
                } else {
                    $isValid = in_array($command, $composerScripts, true) || in_array($command, $builtInComposerCommands, true);
                    if (! $isValid) {
                        $errors[] = "missing direct composer command: {$command}";
                    }
                }
            }
        }

        return $errors;
    };

    // Valid commands pass
    expect($validate('run `bun run build` and `composer test:lint` and `composer run dev`'))->toBeEmpty();

    // Explicit run-script with options resolves to target script
    expect($validate('run `composer run-script --timeout=0 dev`'))->toBeEmpty();

    // Suffix .missing on an existing bun script is captured completely and rejected
    expect($validate('run `bun run build.missing`'))->toBe(['missing bun script: build.missing']);

    // Suffix .missing on an existing composer script is captured completely and rejected
    expect($validate('run `composer test:lint.missing`'))->toBe(['missing direct composer command: test:lint.missing']);

    // Explicit run-script with missing script is rejected
    expect($validate('run `composer run-script missing-script`'))->toBe(['missing explicit composer script: missing-script']);

    // Explicit run with built-in command is rejected as it must be a defined composer script
    expect($validate('run `composer run install`'))->toBe(['missing explicit composer script: install']);

    // Multiline command regression: second composer command on a new line is not swallowed as an argument
    expect($validate("composer install\ncomposer test:lint.missing"))->toBe(['missing direct composer command: test:lint.missing']);

    // Same-line chained commands with shell operators are parsed and validated independently
    expect($validate('composer install && composer test:lint.missing'))->toBe(['missing direct composer command: test:lint.missing']);
});
