<?php

declare(strict_types=1);

use App\Chisel\Installer\Cleanup;

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
                    @unlink($path);
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
