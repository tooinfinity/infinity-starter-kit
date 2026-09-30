<?php

declare(strict_types=1);

use Laravel\Chisel\Chisel;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../../../chisel.php';

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/chisel_json_test_'.bin2hex(random_bytes(6));
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        $cleanup = function (string $dir) use (&$cleanup): void {
            $items = scandir($dir);
            if ($items === false) {
                return;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $dir.'/'.$item;
                if (is_dir($path)) {
                    $cleanup($path);
                } else {
                    @unlink($path);
                }
            }

            @rmdir($dir);
        };
        $cleanup($this->tempDir);
    }
});

test('chiselRemoveFrontendPackages removes package and produces valid JSON', function (): void {
    $packageJson = [
        'name' => 'test-project',
        'dependencies' => [
            '@erag/lang-sync-inertia' => '^3.1.0',
            'react' => '^19.3.0',
        ],
        'devDependencies' => [
            'typescript' => '^7.0.2',
        ],
    ];

    $file = $this->tempDir.'/package.json';
    file_put_contents($file, json_encode($packageJson, JSON_PRETTY_PRINT));

    // Force no node mode for unit test
    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    try {
        $chisel = Chisel::in($this->tempDir);

        chiselRemoveFrontendPackages($this->tempDir, $chisel, '@erag/lang-sync-inertia');

        $contents = (string) file_get_contents($file);
        $decoded = json_decode($contents, true);

        expect($decoded)->toBeArray()
            ->and($decoded['dependencies'])->not->toHaveKey('@erag/lang-sync-inertia')
            ->and($decoded['dependencies'])->toHaveKey('react')
            ->and($decoded['devDependencies'])->toHaveKey('typescript')
            ->and(json_last_error())->toBe(JSON_ERROR_NONE);
    } finally {
        putenv('LARAVEL_INSTALLER_NO_NODE');
        unset($GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'], $GLOBALS['_SERVER']['LARAVEL_INSTALLER_NO_NODE']);
    }
});

test('chiselRemoveFrontendPackages handles missing file gracefully', function (): void {
    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    try {
        $chisel = Chisel::in($this->tempDir);

        expect(fn () => chiselRemoveFrontendPackages($this->tempDir, $chisel, 'some-pkg'))
            ->not->toThrow(Throwable::class);
    } finally {
        putenv('LARAVEL_INSTALLER_NO_NODE');
        unset($GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'], $GLOBALS['_SERVER']['LARAVEL_INSTALLER_NO_NODE']);
    }
});

test('chiselRemoveFrontendPackages is idempotent', function (): void {
    $packageJson = [
        'dependencies' => ['react' => '^19.0.0'],
    ];

    $file = $this->tempDir.'/package.json';
    file_put_contents($file, json_encode($packageJson, JSON_PRETTY_PRINT));

    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    try {
        $chisel = Chisel::in($this->tempDir);

        chiselRemoveFrontendPackages($this->tempDir, $chisel, 'non-existent-package');
        chiselRemoveFrontendPackages($this->tempDir, $chisel, 'non-existent-package');

        $decoded = json_decode((string) file_get_contents($file), true);
        expect($decoded['dependencies'])->toHaveKey('react');
    } finally {
        putenv('LARAVEL_INSTALLER_NO_NODE');
        unset($GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'], $GLOBALS['_SERVER']['LARAVEL_INSTALLER_NO_NODE']);
    }
});

test('chiselRemoveComposerPackages removes packages and produces valid JSON', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'php' => '^8.5.0',
            'spatie/laravel-permission' => '^8.3',
            'spatie/laravel-data' => '^4.23',
        ],
        'require-dev' => [
            'pestphp/pest' => '^5.2.1',
        ],
    ];

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode($composerJson, JSON_PRETTY_PRINT));

    chiselRemoveComposerPackages($this->tempDir, 'spatie/laravel-permission', 'spatie/laravel-data');

    $contents = (string) file_get_contents($file);
    $decoded = json_decode($contents, true);

    expect($decoded)->toBeArray()
        ->and($decoded['require'])->not->toHaveKey('spatie/laravel-permission')
        ->and($decoded['require'])->not->toHaveKey('spatie/laravel-data')
        ->and($decoded['require'])->toHaveKey('php')
        ->and($decoded['require-dev'])->toHaveKey('pestphp/pest')
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);
});

test('chiselRemoveComposerPackages handles missing file and is idempotent', function (): void {
    expect(fn () => chiselRemoveComposerPackages($this->tempDir, 'some/pkg'))
        ->not->toThrow(Throwable::class);

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode(['require' => ['php' => '^8.5']], JSON_PRETTY_PRINT));

    chiselRemoveComposerPackages($this->tempDir, 'unknown/package');
    chiselRemoveComposerPackages($this->tempDir, 'unknown/package');

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['require'])->toHaveKey('php');
});

test('chiselCleanComposerPostCreate removes chisel dependency and install:features script', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'php' => '^8.5.0',
            'laravel/chisel' => '^0.1.1',
        ],
        'scripts' => [
            'post-create-project-cmd' => [
                '@php artisan key:generate --ansi',
                '@php artisan install:features --ansi',
            ],
        ],
    ];

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode($composerJson, JSON_PRETTY_PRINT));

    chiselCleanComposerPostCreate($this->tempDir);

    $contents = (string) file_get_contents($file);
    $decoded = json_decode($contents, true);

    expect($decoded)->toBeArray()
        ->and($decoded['require'])->not->toHaveKey('laravel/chisel')
        ->and($decoded['scripts']['post-create-project-cmd'])->toBe([
            '@php artisan key:generate --ansi',
        ])
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);
});

test('chiselCleanComposerPostCreate handles missing file and idempotent runs gracefully', function (): void {
    expect(fn () => chiselCleanComposerPostCreate($this->tempDir))
        ->not->toThrow(Throwable::class);

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode([
        'scripts' => ['post-create-project-cmd' => ['@php artisan test']],
    ], JSON_PRETTY_PRINT));

    chiselCleanComposerPostCreate($this->tempDir);
    chiselCleanComposerPostCreate($this->tempDir);

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['scripts']['post-create-project-cmd'])->toBe(['@php artisan test']);
});

test('chiselCleanPhpstanConfig removes chisel.php and empty bootstrapFiles when only chisel.php is present', function (): void {
    $neon = <<<'NEON'
parameters:

    bootstrapFiles:
        - chisel.php

    paths:
        - app
NEON;

    $file = $this->tempDir.'/phpstan.neon';
    file_put_contents($file, $neon);

    chiselCleanPhpstanConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    $parsed = Yaml::parse($contents);

    expect($contents)->not->toContain('chisel.php')
        ->and($contents)->not->toContain('bootstrapFiles:')
        ->and($contents)->toContain('paths:')
        ->and($parsed)->toBeArray()
        ->and($parsed['parameters'])->not->toHaveKey('bootstrapFiles')
        ->and($parsed['parameters']['paths'])->toBe(['app']);
});

test('chiselCleanPhpstanConfig preserves other bootstrapFiles entries when multiple bootstrap files exist', function (): void {
    $neon = <<<'NEON'
parameters:
    bootstrapFiles:
        - first-bootstrap.php
        - chisel.php
        - second-bootstrap.php
    paths:
        - app
NEON;

    $file = $this->tempDir.'/phpstan.neon';
    file_put_contents($file, $neon);

    chiselCleanPhpstanConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    $parsed = Yaml::parse($contents);

    expect($contents)->not->toContain('chisel.php')
        ->and($contents)->toContain('first-bootstrap.php')
        ->and($contents)->toContain('second-bootstrap.php')
        ->and($contents)->toContain('bootstrapFiles:')
        ->and($parsed['parameters']['bootstrapFiles'])->toBe([
            'first-bootstrap.php',
            'second-bootstrap.php',
        ]);
});

test('chiselCleanPhpstanConfig leaves already-clean config unchanged', function (): void {
    $neon = <<<'NEON'
parameters:
    bootstrapFiles:
        - custom-bootstrap.php
    paths:
        - app
    level: max
NEON;

    $file = $this->tempDir.'/phpstan.neon';
    file_put_contents($file, $neon);

    chiselCleanPhpstanConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    $parsed = Yaml::parse($contents);

    expect($contents)->toBe($neon)
        ->and($parsed['parameters']['bootstrapFiles'])->toBe(['custom-bootstrap.php']);
});

test('chiselCleanPhpstanConfig handles config with no bootstrapFiles section', function (): void {
    $neon = <<<'NEON'
parameters:
    paths:
        - app
        - config
    level: max
NEON;

    $file = $this->tempDir.'/phpstan.neon';
    file_put_contents($file, $neon);

    chiselCleanPhpstanConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    $parsed = Yaml::parse($contents);

    expect($contents)->toBe($neon)
        ->and($parsed['parameters']['paths'])->toBe(['app', 'config']);
});

test('chiselCleanPhpstanConfig handles different indentation styles (2 spaces, 8 spaces, tabs)', function (): void {
    $twoSpaceNeon = "parameters:\n  bootstrapFiles:\n    - chisel.php\n  paths:\n    - app\n";
    $file = $this->tempDir.'/phpstan.neon';
    file_put_contents($file, $twoSpaceNeon);

    chiselCleanPhpstanConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    $parsed = Yaml::parse($contents);
    expect($contents)->not->toContain('chisel.php')
        ->and($contents)->not->toContain('bootstrapFiles:')
        ->and($parsed['parameters']['paths'])->toBe(['app']);

    $tabNeon = "parameters:\n\tbootstrapFiles:\n\t\t- chisel.php\n\t\t- keep.php\n\tpaths:\n\t\t- app\n";
    file_put_contents($file, $tabNeon);

    chiselCleanPhpstanConfig($this->tempDir);

    $tabContents = (string) file_get_contents($file);
    $normalizedTab = preg_replace_callback('/^\t+/m', fn (array $m): string => str_repeat('    ', mb_strlen($m[0])), $tabContents) ?? $tabContents;
    $parsedTab = Yaml::parse($normalizedTab);

    expect($tabContents)->not->toContain('chisel.php')
        ->and($tabContents)->toContain('keep.php')
        ->and($parsedTab['parameters']['bootstrapFiles'])->toBe(['keep.php']);
});

test('chiselCleanPhpstanConfig handles CRLF line endings cleanly', function (): void {
    $crlfNeon = "parameters:\r\n\r\n    bootstrapFiles:\r\n        - chisel.php\r\n\r\n    paths:\r\n        - app\r\n";
    $file = $this->tempDir.'/phpstan.neon';
    file_put_contents($file, $crlfNeon);

    chiselCleanPhpstanConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    $parsed = Yaml::parse($contents);

    expect($contents)->not->toContain('chisel.php')
        ->and($contents)->not->toContain('bootstrapFiles:')
        ->and($contents)->toContain("\r\n")
        ->and($parsed['parameters']['paths'])->toBe(['app']);
});

test('chiselCleanPhpunitConfig removes InstallFeaturesCommand exclusion and empty exclude tag', function (): void {
    $phpunitXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <source>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <file>app/Console/Commands/InstallFeaturesCommand.php</file>
        </exclude>
    </source>
</phpunit>
XML;

    $file = $this->tempDir.'/phpunit.xml';
    file_put_contents($file, $phpunitXml);

    chiselCleanPhpunitConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    $xml = simplexml_load_string($contents);

    expect($contents)->not->toContain('InstallFeaturesCommand.php')
        ->and($contents)->not->toContain('<exclude>')
        ->and($xml)->not->toBeFalse();
});
