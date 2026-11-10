<?php

declare(strict_types=1);

use App\Chisel\Installer\Cleanup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

require_once __DIR__.'/../../../chisel.php';

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/cleanup_test_'.bin2hex(random_bytes(6));
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
                    File::delete($path);
                }
            }

            @rmdir($dir);
        };
        $cleanup($this->tempDir);
    }
});

test('cleanup clean removes declared installer files, directories, and syncs configs', function (): void {
    $fileToDelete = $this->tempDir.'/chisel-temp.php';
    file_put_contents($fileToDelete, '<?php // delete');

    $emptyDir = $this->tempDir.'/app/ChiselTemp';
    mkdir($emptyDir, 0777, true);

    $phpunitXml = $this->tempDir.'/phpunit.xml';
    file_put_contents($phpunitXml, '<phpunit><source><exclude><file>app/Console/Commands/InstallFeaturesCommand.php</file></exclude></source></phpunit>');

    $phpstanNeon = $this->tempDir.'/phpstan.neon';
    file_put_contents($phpstanNeon, "parameters:\n    bootstrapFiles:\n        - chisel.php\n");

    $composerJson = $this->tempDir.'/composer.json';
    file_put_contents($composerJson, json_encode([
        'name' => 'test/app',
        'require-dev' => ['laravel/chisel' => '^0.1'],
        'scripts' => ['post-create-project-cmd' => ['@php artisan install:features']],
    ]));

    $paths = [
        'chisel' => [
            'files' => ['chisel-temp.php'],
            'empty_dirs' => ['app/ChiselTemp'],
        ],
    ];

    Cleanup::clean($this->tempDir, $paths);

    expect(file_exists($fileToDelete))->toBeFalse()
        ->and(is_dir($emptyDir))->toBeFalse()
        ->and((string) file_get_contents($phpunitXml))->not->toContain('InstallFeaturesCommand')
        ->and((string) file_get_contents($phpstanNeon))->not->toContain('chisel.php');
});

test('cleanup clean succeeds idempotently when declared files and directories are already absent', function (): void {
    $paths = [
        'chisel' => [
            'files' => ['already-missing-file.php', 'another-missing.php'],
            'empty_dirs' => ['app/AlreadyMissingDir', 'tests/MissingDir'],
        ],
    ];

    expect(fn () => Cleanup::clean($this->tempDir, $paths))->not->toThrow(Throwable::class);
});

test('cleanup clean rethrows ComposerLockSynchronizer failure with recovery guidance', function (): void {
    $composerJson = [
        'name' => 'test/project',
        'require' => ['php' => '^8.5.0'],
    ];
    $composerLock = [
        'packages' => [['name' => 'spatie/laravel-permission']],
        'packages-dev' => [],
    ];
    file_put_contents($this->tempDir.'/composer.json', json_encode($composerJson));
    file_put_contents($this->tempDir.'/composer.lock', json_encode($composerLock));

    $paths = [
        'authorization' => ['composer_package' => 'spatie/laravel-permission'],
        'chisel' => [
            'files' => [],
            'empty_dirs' => [],
        ],
    ];

    expect(fn () => Cleanup::clean($this->tempDir, $paths))
        ->toThrow(RuntimeException::class, 'Installer files were already removed. Run `composer update --with-all-dependencies`, then remove `.installer-state.json`.');
});

test('cleanup updateAiGuidelines returns early when required boost artifacts or directories are absent', function (): void {
    // Missing all
    expect(fn () => Cleanup::updateAiGuidelines($this->tempDir))->not->toThrow(Throwable::class);

    // Missing guidelines dir
    file_put_contents($this->tempDir.'/boost.json', '{}');
    expect(fn () => Cleanup::updateAiGuidelines($this->tempDir))->not->toThrow(Throwable::class);

    // Missing artisan
    mkdir($this->tempDir.'/.ai/guidelines', 0777, true);
    expect(fn () => Cleanup::updateAiGuidelines($this->tempDir))->not->toThrow(Throwable::class);

    // Missing vendor/laravel/boost
    touch($this->tempDir.'/artisan');
    expect(fn () => Cleanup::updateAiGuidelines($this->tempDir))->not->toThrow(Throwable::class);
});

test('cleanup updateAiGuidelines executes boost:update when all prerequisites exist', function (): void {
    file_put_contents($this->tempDir.'/boost.json', '{}');
    mkdir($this->tempDir.'/.ai/guidelines', 0777, true);
    touch($this->tempDir.'/artisan');
    mkdir($this->tempDir.'/vendor/laravel/boost', 0777, true);

    Process::fake([
        '*' => Process::result(output: 'Boost updated'),
    ]);

    Cleanup::updateAiGuidelines($this->tempDir);

    Process::assertRan(function ($process): bool {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

        return str_contains($cmd, 'boost:update')
            && str_contains($cmd, '--ignore-skills');
    });
});
