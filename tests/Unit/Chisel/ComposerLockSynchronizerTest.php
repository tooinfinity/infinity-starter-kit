<?php

declare(strict_types=1);

use App\Chisel\Installer\ComposerLockSynchronizer;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/composer_lock_test_'.bin2hex(random_bytes(6));
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

test('composer lock synchronizer returns early if composer.json or composer.lock is missing', function (): void {
    expect(fn () => ComposerLockSynchronizer::sync($this->tempDir))->not->toThrow(Throwable::class);

    file_put_contents($this->tempDir.'/composer.json', '{}');
    expect(fn () => ComposerLockSynchronizer::sync($this->tempDir))->not->toThrow(Throwable::class);
});

test('composer lock synchronizer returns early if no candidate packages were removed', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'laravel/chisel' => '^0.1',
            'spatie/laravel-permission' => '^8.3',
        ],
    ];

    $composerLock = [
        'packages' => [
            ['name' => 'laravel/chisel'],
            ['name' => 'spatie/laravel-permission'],
        ],
        'packages-dev' => [],
    ];

    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));

    $paths = [
        'auth' => ['composer_package' => 'spatie/laravel-permission'],
        'data' => 'not-an-array',
        'extra' => ['composer_packages' => ['spatie/laravel-data', 123]],
    ];

    expect(fn () => ComposerLockSynchronizer::sync($this->tempDir, $paths))->not->toThrow(Throwable::class);
});

test('composer lock synchronizer detects removed candidates and fails with RuntimeException on composer failure', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'php' => '^8.5.0',
        ],
    ];

    $composerLock = [
        'packages' => [
            ['name' => 'spatie/laravel-permission'],
            ['name' => 'spatie/laravel-data'],
        ],
        'packages-dev' => [],
    ];

    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));

    $paths = [
        'authorization' => ['composer_package' => 'spatie/laravel-permission'],
        'data' => ['composer_packages' => ['spatie/laravel-data']],
    ];

    expect(fn () => ComposerLockSynchronizer::sync($this->tempDir, $paths))
        ->toThrow(RuntimeException::class, 'Failed to synchronize composer.lock');
});

test('composer lock synchronizer falls back to FeatureRegistry when paths is null and chisel-paths.php does not exist', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'php' => '^8.5.0',
        ],
    ];

    $composerLock = [
        'packages' => [
            ['name' => 'laravel/chisel'],
        ],
        'packages-dev' => [],
    ];

    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));

    expect(fn () => ComposerLockSynchronizer::sync($this->tempDir))
        ->toThrow(RuntimeException::class, 'Failed to synchronize composer.lock');
});

test('composer lock synchronizer loads chisel-paths.php when paths is null and chisel-paths.php exists', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => [
            'php' => '^8.5.0',
        ],
    ];

    $composerLock = [
        'packages' => [
            ['name' => 'custom/pkg'],
        ],
    ];

    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));
    file_put_contents($this->tempDir.'/chisel-paths.php', '<?php return ["custom" => ["composer_package" => "custom/pkg"]];');

    expect(fn () => ComposerLockSynchronizer::sync($this->tempDir))
        ->toThrow(RuntimeException::class, 'Failed to synchronize composer.lock');
});

test('composer lock synchronizer cleans bootstrap caches and triggers package discovery when real vendor exists', function (): void {
    $binDir = $this->tempDir.'/bin';
    mkdir($binDir, 0777, true);
    $mockComposer = $binDir.'/composer';
    file_put_contents($mockComposer, "#!/bin/sh\nexit 0\n");
    chmod($mockComposer, 0755);

    $vendorDir = $this->tempDir.'/vendor';
    mkdir($vendorDir, 0777, true);

    $bootstrapDir = $this->tempDir.'/bootstrap/cache';
    mkdir($bootstrapDir, 0777, true);
    file_put_contents($bootstrapDir.'/packages.php', '<?php return [];');
    file_put_contents($bootstrapDir.'/services.php', '<?php return [];');

    $mockArtisan = $this->tempDir.'/artisan';
    file_put_contents($mockArtisan, "<?php exit(0);\n");
    chmod($mockArtisan, 0755);

    $composerJson = [
        'name' => 'test/project',
        'require' => ['php' => '^8.5.0'],
    ];

    $composerLock = [
        'packages' => [['name' => 'spatie/laravel-permission']],
    ];

    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));

    $paths = [
        'authorization' => ['composer_package' => 'spatie/laravel-permission'],
    ];

    $originalPath = getenv('PATH') ?: '';
    putenv('PATH='.$binDir.':'.$originalPath);
    $_SERVER['PATH'] = $binDir.':'.$originalPath;
    $_ENV['PATH'] = $binDir.':'.$originalPath;

    try {
        ComposerLockSynchronizer::sync($this->tempDir, $paths);

        expect(file_exists($bootstrapDir.'/packages.php'))->toBeFalse()
            ->and(file_exists($bootstrapDir.'/services.php'))->toBeFalse();
    } finally {
        putenv('PATH='.$originalPath);
        $_SERVER['PATH'] = $originalPath;
        $_ENV['PATH'] = $originalPath;
    }
});

test('composer lock synchronizer discovers feature packages from FeatureRegistry even with empty paths array', function (): void {
    $binDir = $this->tempDir.'/bin';
    mkdir($binDir, 0777, true);
    $capturedArgsFile = $this->tempDir.'/composer_args.txt';
    $mockComposer = $binDir.'/composer';
    file_put_contents($mockComposer, "#!/bin/sh\necho \"$@\" > ".escapeshellarg($capturedArgsFile)."\nexit 0\n");
    chmod($mockComposer, 0755);

    $composerJson = [
        'name' => 'test/project',
        'require' => ['php' => '^8.5.0'],
    ];

    $composerLock = [
        'packages' => [
            ['name' => 'spatie/laravel-permission'],
            ['name' => 'unrelated/package'],
        ],
    ];

    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));

    $originalPath = getenv('PATH') ?: '';
    putenv('PATH='.$binDir.':'.$originalPath);
    $_SERVER['PATH'] = $binDir.':'.$originalPath;
    $_ENV['PATH'] = $binDir.':'.$originalPath;

    try {
        // Pass empty paths array; candidate should still be discovered from FeatureRegistry::allComposerPackages()
        ComposerLockSynchronizer::sync($this->tempDir, []);

        expect(file_exists($capturedArgsFile))->toBeTrue();
        $capturedArgs = (string) file_get_contents($capturedArgsFile);
        expect($capturedArgs)->toContain('spatie/laravel-permission')
            ->and($capturedArgs)->not->toContain('unrelated/package');
    } finally {
        putenv('PATH='.$originalPath);
        $_SERVER['PATH'] = $originalPath;
        $_ENV['PATH'] = $originalPath;
    }
});

test('composer lock synchronizer formats exception from standard output when error output is empty', function (): void {
    $binDir = $this->tempDir.'/bin';
    mkdir($binDir, 0777, true);
    $mockComposer = $binDir.'/composer';
    file_put_contents($mockComposer, "#!/bin/sh\necho \"stdout error details\"\nexit 1\n");
    chmod($mockComposer, 0755);

    $composerJson = [
        'name' => 'test/project',
        'require' => ['php' => '^8.5.0'],
    ];

    $composerLock = [
        'packages' => [
            ['name' => 'spatie/laravel-permission'],
        ],
    ];

    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));

    $originalPath = getenv('PATH') ?: '';
    putenv('PATH='.$binDir.':'.$originalPath);
    $_SERVER['PATH'] = $binDir.':'.$originalPath;
    $_ENV['PATH'] = $binDir.':'.$originalPath;

    try {
        expect(fn () => ComposerLockSynchronizer::sync($this->tempDir))
            ->toThrow(RuntimeException::class, 'Failed to synchronize composer.lock: stdout error details');
    } finally {
        putenv('PATH='.$originalPath);
        $_SERVER['PATH'] = $originalPath;
        $_ENV['PATH'] = $originalPath;
    }
});
