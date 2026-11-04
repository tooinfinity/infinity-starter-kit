<?php

declare(strict_types=1);

use App\Chisel\Installer\ComposerManifestPruner;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/composer_manifest_test_'.bin2hex(random_bytes(6));
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

test('composer manifest pruner removes packages from require and require-dev and leaves valid JSON', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'php' => '^8.5.0',
            'spatie/laravel-permission' => '^8.3',
        ],
        'require-dev' => [
            'pestphp/pest' => '^5.0',
            'spatie/laravel-data' => '^4.0',
        ],
    ];

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode($composerJson, JSON_PRETTY_PRINT));

    ComposerManifestPruner::removePackages($this->tempDir, 'spatie/laravel-permission', 'spatie/laravel-data');

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['require'])->not->toHaveKey('spatie/laravel-permission')
        ->and($decoded['require'])->toHaveKey('php')
        ->and($decoded['require-dev'])->not->toHaveKey('spatie/laravel-data')
        ->and($decoded['require-dev'])->toHaveKey('pestphp/pest');
});

test('composer manifest pruner returns early if composer.json does not exist', function (): void {
    expect(fn () => ComposerManifestPruner::removePackages($this->tempDir, 'dummy/package'))
        ->not->toThrow(Throwable::class);

    expect(fn () => ComposerManifestPruner::cleanPostCreate($this->tempDir))
        ->not->toThrow(Throwable::class);
});

test('composer manifest pruner cleanPostCreate removes chisel, install:features, and template-only scripts', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'laravel/chisel' => '^0.1',
        ],
        'require-dev' => [
            'laravel/chisel' => '^0.1',
        ],
        'scripts' => [
            'post-create-project-cmd' => [
                '@php artisan key:generate --ansi',
                '@php artisan install:features --ansi',
                123, // non-string entry to test filter
            ],
            'docs:generate' => '@php artisan chisel:docs',
            'docs:check' => '@php artisan chisel:docs --check',
            'test:installer' => 'pest --group=installer-e2e --compact',
            'test:smoke' => 'pest --group=installer-smoke --compact',
            'test:lint' => [
                'pint --parallel --test',
                'rector --dry-run',
                'bun run test:lint',
                '@php artisan chisel:docs --check',
                456, // non-string entry
            ],
            'test:coverage' => 'XDEBUG_MODE="coverage" pest --parallel --coverage --exactly=100.0 --exclude-testsuite=Browser --exclude-group=installer-e2e --exclude-group=installer-smoke',
            'test:quick' => 'pest --parallel --exclude-testsuite=Browser --exclude-group=installer-e2e --exclude-group=installer-smoke',
        ],
    ];

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode($composerJson, JSON_PRETTY_PRINT));

    ComposerManifestPruner::cleanPostCreate($this->tempDir);

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['require'])->not->toHaveKey('laravel/chisel')
        ->and($decoded['require-dev'])->not->toHaveKey('laravel/chisel')
        ->and($decoded['scripts']['post-create-project-cmd'])->toBe(['@php artisan key:generate --ansi'])
        ->and($decoded['scripts'])->not->toHaveKey('docs:generate')
        ->and($decoded['scripts'])->not->toHaveKey('docs:check')
        ->and($decoded['scripts'])->not->toHaveKey('test:installer')
        ->and($decoded['scripts'])->not->toHaveKey('test:smoke')
        ->and($decoded['scripts']['test:lint'])->toBe([
            'pint --parallel --test',
            'rector --dry-run',
            'bun run test:lint',
        ])
        ->and($decoded['scripts']['test:coverage'])->toBe('XDEBUG_MODE="coverage" pest --parallel --coverage --exactly=100.0 --exclude-testsuite=Browser')
        ->and($decoded['scripts']['test:quick'])->toBe('pest --parallel --exclude-testsuite=Browser');
});

test('composer manifest pruner cleanPostCreate handles composer.json without scripts', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require-dev' => [
            'laravel/chisel' => '^0.1',
        ],
    ];

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode($composerJson, JSON_PRETTY_PRINT));

    ComposerManifestPruner::cleanPostCreate($this->tempDir);

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['require-dev'])->not->toHaveKey('laravel/chisel');
});

test('composer manifest pruner cleanPostCreate removes separate standalone exclude-group flags from test scripts', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'scripts' => [
            'test:coverage' => 'pest --coverage --exclude-group=installer-e2e --exclude-group=installer-smoke --min=100',
            'test:quick' => 'pest --parallel --exclude-group=installer-smoke',
        ],
    ];

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode($composerJson, JSON_PRETTY_PRINT));

    ComposerManifestPruner::cleanPostCreate($this->tempDir);

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['scripts']['test:coverage'])->toBe('pest --coverage --min=100')
        ->and($decoded['scripts']['test:quick'])->toBe('pest --parallel');
});
