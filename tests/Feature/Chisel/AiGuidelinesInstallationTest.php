<?php

declare(strict_types=1);

require_once __DIR__.'/../../../chisel.php';

use App\Chisel\Installer\AiToolingPruner;
use Laravel\Chisel\Chisel;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/ai_guidelines_install_test_'.bin2hex(random_bytes(6));
    mkdir($this->tempDir, 0777, true);

    // Create dummy versions of all AI tooling files and directories
    foreach (AiToolingPruner::files() as $file) {
        $path = $this->tempDir.'/'.$file;
        file_put_contents($path, 'dummy content');
    }

    foreach (AiToolingPruner::directories() as $dir) {
        $dirPath = $this->tempDir.'/'.$dir;
        mkdir($dirPath.'/nested', 0777, true);
        file_put_contents($dirPath.'/nested/rule.md', 'dummy rule');
    }

    // Unrelated file
    file_put_contents($this->tempDir.'/composer.json', '{"name":"test/app"}');
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        AiToolingPruner::prune($this->tempDir);
        @unlink($this->tempDir.'/composer.json');
        @rmdir($this->tempDir);
    }
});

test('chisel prunes AI tooling files and directories when ai_guidelines is empty', function (): void {
    $script = Chisel::script($this->tempDir)
        ->selected(
            'ai_guidelines',
            'guidelines',
            else: function (Chisel $chisel): void {
                chiselPruneAiTooling($this->tempDir);
            },
        );

    $script->chisel([
        'ai_guidelines' => [],
    ]);

    foreach (AiToolingPruner::files() as $file) {
        expect(file_exists($this->tempDir.'/'.$file))->toBeFalse("File [{$file}] should have been pruned.");
    }

    foreach (AiToolingPruner::directories() as $dir) {
        expect(file_exists($this->tempDir.'/'.$dir))->toBeFalse("Directory [{$dir}] should have been pruned.");
    }

    expect(file_exists($this->tempDir.'/composer.json'))->toBeTrue();
});

test('chisel retains AI tooling files and directories when ai_guidelines is selected', function (): void {
    $script = Chisel::script($this->tempDir)
        ->selected(
            'ai_guidelines',
            'guidelines',
            else: function (Chisel $chisel): void {
                chiselPruneAiTooling($this->tempDir);
            },
        );

    $script->chisel([
        'ai_guidelines' => ['guidelines'],
    ]);

    foreach (AiToolingPruner::files() as $file) {
        expect(file_exists($this->tempDir.'/'.$file))->toBeTrue("File [{$file}] should have been retained.");
    }

    foreach (AiToolingPruner::directories() as $dir) {
        expect(is_dir($this->tempDir.'/'.$dir))->toBeTrue("Directory [{$dir}] should have been retained.");
    }
});
