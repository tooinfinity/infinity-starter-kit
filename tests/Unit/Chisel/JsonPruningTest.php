<?php

declare(strict_types=1);

use Laravel\Chisel\Chisel;

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
    $chisel = Chisel::in($this->tempDir);

    expect(fn () => chiselRemoveFrontendPackages($this->tempDir, $chisel, 'some-pkg'))
        ->not->toThrow(Throwable::class);
});

test('chiselRemoveFrontendPackages is idempotent', function (): void {
    $packageJson = [
        'dependencies' => ['react' => '^19.0.0'],
    ];

    $file = $this->tempDir.'/package.json';
    file_put_contents($file, json_encode($packageJson, JSON_PRETTY_PRINT));

    $chisel = Chisel::in($this->tempDir);

    chiselRemoveFrontendPackages($this->tempDir, $chisel, 'non-existent-package');
    chiselRemoveFrontendPackages($this->tempDir, $chisel, 'non-existent-package');

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['dependencies'])->toHaveKey('react');
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

test('chiselCleanPhpstanConfig removes chisel.php from bootstrapFiles', function (): void {
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
    expect($contents)->not->toContain('chisel.php')
        ->and($contents)->toContain('paths:');
});

test('chiselCleanPhpstanConfig preserves other bootstrapFiles entries', function (): void {
    $neon = <<<'NEON'
parameters:
    bootstrapFiles:
        - chisel.php
        - other-bootstrap.php
    paths:
        - app
NEON;

    $file = $this->tempDir.'/phpstan.neon';
    file_put_contents($file, $neon);

    chiselCleanPhpstanConfig($this->tempDir);

    $contents = (string) file_get_contents($file);
    expect($contents)->not->toContain('chisel.php')
        ->and($contents)->toContain('other-bootstrap.php')
        ->and($contents)->toContain('bootstrapFiles:');
});
