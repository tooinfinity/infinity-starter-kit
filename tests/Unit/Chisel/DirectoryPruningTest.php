<?php

declare(strict_types=1);

require_once __DIR__.'/../../../chisel.php';

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/chisel_prune_test_'.bin2hex(random_bytes(6));
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

test('prunes empty directory', function (): void {
    $target = $this->tempDir.'/app/EmptyDir';
    mkdir($target, 0777, true);

    expect(is_dir($target))->toBeTrue();

    chiselPruneEmptyDirectories($this->tempDir, ['app/EmptyDir']);

    expect(is_dir($target))->toBeFalse();
});

test('does not prune non-empty directory', function (): void {
    $target = $this->tempDir.'/app/NonEmptyDir';
    mkdir($target, 0777, true);
    file_put_contents($target.'/file.php', '<?php');

    chiselPruneEmptyDirectories($this->tempDir, ['app/NonEmptyDir']);

    expect(is_dir($target))->toBeTrue()
        ->and(file_exists($target.'/file.php'))->toBeTrue();
});

test('prunes nested empty directories walking upward', function (): void {
    $nested = $this->tempDir.'/resources/js/pages/nested/deep';
    mkdir($nested, 0777, true);

    chiselPruneEmptyDirectories($this->tempDir, ['resources/js/pages/nested/deep']);

    expect(is_dir($nested))->toBeFalse()
        ->and(is_dir($this->tempDir.'/resources/js/pages/nested'))->toBeFalse();
});

test('does not prune directory containing hidden files', function (): void {
    $target = $this->tempDir.'/app/HiddenDir';
    mkdir($target, 0777, true);
    file_put_contents($target.'/.hidden_file', 'secret');

    chiselPruneEmptyDirectories($this->tempDir, ['app/HiddenDir']);

    expect(is_dir($target))->toBeTrue();
});

test('does not prune directory containing .gitkeep', function (): void {
    $target = $this->tempDir.'/app/KeepDir';
    mkdir($target, 0777, true);
    file_put_contents($target.'/.gitkeep', '');

    chiselPruneEmptyDirectories($this->tempDir, ['app/KeepDir']);

    expect(is_dir($target))->toBeTrue();
});

test('handles missing directory gracefully', function (): void {
    expect(fn () => chiselPruneEmptyDirectories($this->tempDir, ['app/NonExistentDir']))
        ->not->toThrow(Throwable::class);
});

test('never deletes project root directory or protected top-level directories', function (): void {
    $protected = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'storage', 'tests'];

    foreach ($protected as $dir) {
        $path = $this->tempDir.'/'.$dir;
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    chiselPruneEmptyDirectories($this->tempDir, array_merge(['', '.'], $protected));

    expect(is_dir($this->tempDir))->toBeTrue();

    foreach ($protected as $dir) {
        expect(is_dir($this->tempDir.'/'.$dir))->toBeTrue();
    }
});
