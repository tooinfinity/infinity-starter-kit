<?php

declare(strict_types=1);

use App\Chisel\Installer\AiToolingPruner;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/ai_pruner_test_'.bin2hex(random_bytes(6));
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        AiToolingPruner::prune($this->tempDir);
        @rmdir($this->tempDir);
    }
});

test('files returns declared AI tooling and assistant guideline files', function (): void {
    $files = AiToolingPruner::files();

    expect($files)->toBe([
        '.mcp.json',
        'AGENTS.md',
        'CLAUDE.md',
        'GEMINI.md',
        'boost.json',
        'opencode.json',
    ]);
});

test('directories returns declared AI tooling and assistant guideline directories', function (): void {
    $directories = AiToolingPruner::directories();

    expect($directories)->toBe([
        '.agents',
        '.ai',
        '.amp',
        '.claude',
        '.codex',
        '.cursor',
        '.gemini',
        '.junie',
    ]);
});

test('prune removes all declared AI tooling files and directories recursively', function (): void {
    foreach (AiToolingPruner::files() as $file) {
        $filePath = $this->tempDir.'/'.$file;
        file_put_contents($filePath, 'dummy content');
        expect(file_exists($filePath))->toBeTrue();
    }

    foreach (AiToolingPruner::directories() as $dir) {
        $dirPath = $this->tempDir.'/'.$dir;
        $subDirPath = $dirPath.'/nested/deep';
        mkdir($subDirPath, 0777, true);
        file_put_contents($subDirPath.'/config.json', '{"test": true}');
        @symlink($subDirPath.'/config.json', $dirPath.'/nested/symlink.json');
        expect(is_dir($dirPath))->toBeTrue();
    }

    // Unrelated files that must be preserved
    $keepFile = $this->tempDir.'/composer.json';
    file_put_contents($keepFile, '{"name": "test"}');

    $keepDir = $this->tempDir.'/app';
    mkdir($keepDir, 0777, true);
    file_put_contents($keepDir.'/Kernel.php', '<?php');

    AiToolingPruner::prune($this->tempDir);

    foreach (AiToolingPruner::files() as $file) {
        expect(file_exists($this->tempDir.'/'.$file))->toBeFalse("File [{$file}] was not pruned.");
    }

    foreach (AiToolingPruner::directories() as $dir) {
        expect(file_exists($this->tempDir.'/'.$dir))->toBeFalse("Directory [{$dir}] was not pruned.");
    }

    expect(file_exists($keepFile))->toBeTrue()
        ->and(file_exists($keepDir.'/Kernel.php'))->toBeTrue();
});

test('prune succeeds idempotently when declared files and directories are absent', function (): void {
    expect(fn () => AiToolingPruner::prune($this->tempDir))->not->toThrow(Throwable::class);
});
