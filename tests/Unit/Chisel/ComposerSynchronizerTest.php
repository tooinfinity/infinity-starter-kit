<?php

declare(strict_types=1);

use App\Chisel\Installer\ComposerSynchronizer;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/composer_sync_test_'.bin2hex(random_bytes(6));
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

test('composer synchronizer removes package and leaves valid JSON', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'php' => '^8.5.0',
            'spatie/laravel-permission' => '^8.3',
        ],
        'require-dev' => [
            'pestphp/pest' => '^5.0',
        ],
    ];

    $file = $this->tempDir.'/composer.json';
    file_put_contents($file, json_encode($composerJson, JSON_PRETTY_PRINT));

    ComposerSynchronizer::removePackages($this->tempDir, 'spatie/laravel-permission');

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['require'])->not->toHaveKey('spatie/laravel-permission')
        ->and($decoded['require'])->toHaveKey('php');
});

test('composer synchronizer cleanPostCreate removes chisel and install:features', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require-dev' => [
            'laravel/chisel' => '^0.1',
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

    ComposerSynchronizer::cleanPostCreate($this->tempDir);

    $decoded = json_decode((string) file_get_contents($file), true);
    expect($decoded['require-dev'])->not->toHaveKey('laravel/chisel')
        ->and($decoded['scripts']['post-create-project-cmd'])->toBe(['@php artisan key:generate --ansi']);
});
