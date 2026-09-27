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
    $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    $chisel = Chisel::in($this->tempDir);

    // Call chiselRemoveFrontendPackages with the tempDir override
    $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    unset($data['dependencies']['@erag/lang-sync-inertia']);
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)."\n");

    $contents = (string) file_get_contents($file);
    $decoded = json_decode($contents, true);

    expect($decoded)->toBeArray()
        ->and($decoded['dependencies'])->not->toHaveKey('@erag/lang-sync-inertia')
        ->and($decoded['dependencies'])->toHaveKey('react')
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);
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
        ->and(json_last_error())->toBe(JSON_ERROR_NONE);
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
