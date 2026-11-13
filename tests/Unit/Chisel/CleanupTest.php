<?php

declare(strict_types=1);

use App\Chisel\Installer\Cleanup;
use Illuminate\Process\Exceptions\ProcessFailedException;
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
        '*' => Process::result(output: 'boost:update  Update Boost'),
    ]);

    Cleanup::updateAiGuidelines($this->tempDir);

    Process::assertRan(function ($process): bool {
        $cmd = is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;

        return str_contains($cmd, 'boost:update')
            && str_contains($cmd, '--ignore-skills');
    });
});

test('cleanup checks Boost availability before deletion and updates guidelines afterward', function (bool $available): void {
    file_put_contents($this->tempDir.'/boost.json', '{}');
    mkdir($this->tempDir.'/.ai/guidelines', 0777, true);
    touch($this->tempDir.'/artisan');
    mkdir($this->tempDir.'/vendor/laravel/boost', 0777, true);
    $installerFile = $this->tempDir.'/installer.php';
    touch($installerFile);

    Process::fake(function ($process) use ($available, $installerFile) {
        if (in_array('list', $process->command, true)) {
            expect(file_exists($installerFile))->toBeTrue();
            expect($process->environment)->toMatchArray(['APP_ENV' => 'local']);

            return Process::result(output: $available ? 'boost:update  Update Boost' : 'about  Display application information');
        }

        expect(file_exists($installerFile))->toBeFalse();

        return Process::result();
    });

    Cleanup::clean($this->tempDir, ['chisel' => ['files' => ['installer.php'], 'empty_dirs' => []]]);

    expect(file_exists($installerFile))->toBeFalse();
    Process::assertRanTimes(fn (): bool => true, $available ? 2 : 1);
    if ($available) {
        Process::assertRan(fn ($process): bool => in_array('boost:update', $process->command, true));
    } else {
        Process::assertNotRan(fn ($process): bool => in_array('boost:update', $process->command, true));
    }
})->with([true, false]);

test('cleanup preserves installer files when command discovery fails', function (): void {
    file_put_contents($this->tempDir.'/boost.json', '{}');
    mkdir($this->tempDir.'/.ai/guidelines', 0777, true);
    touch($this->tempDir.'/artisan');
    mkdir($this->tempDir.'/vendor/laravel/boost', 0777, true);
    touch($this->tempDir.'/installer.php');
    Process::fake(['*' => Process::result(exitCode: 1)]);

    expect(fn () => Cleanup::clean($this->tempDir, ['chisel' => ['files' => ['installer.php']]]))
        ->toThrow(ProcessFailedException::class);
    expect(file_exists($this->tempDir.'/installer.php'))->toBeTrue();
});

test('cleanup allows required steps to complete when discovery succeeds but guideline update fails', function (): void {
    file_put_contents($this->tempDir.'/boost.json', '{}');
    mkdir($this->tempDir.'/.ai/guidelines', 0777, true);
    touch($this->tempDir.'/artisan');
    mkdir($this->tempDir.'/vendor/laravel/boost', 0777, true);
    $installerFile = $this->tempDir.'/installer.php';
    touch($installerFile);

    Process::fake(function ($process) {
        if (in_array('list', $process->command, true)) {
            return Process::result(output: 'boost:update  Update Boost');
        }

        return Process::result(output: 'Update failed', exitCode: 1);
    });

    Cleanup::clean($this->tempDir, ['chisel' => ['files' => ['installer.php'], 'empty_dirs' => []]]);

    expect(file_exists($installerFile))->toBeFalse();
    Process::assertRan(fn ($process): bool => in_array('boost:update', $process->command, true));
});

test('cleanup files list does not contain LICENSE', function (): void {
    expect(Cleanup::files())->not->toContain('LICENSE');
});

test('cleanup files list contains matrix test runner scripts', function (): void {
    expect(Cleanup::files())
        ->toContain('scripts/test-feature-matrix.sh')
        ->toContain('scripts/test-feature-matrix.php');
});
