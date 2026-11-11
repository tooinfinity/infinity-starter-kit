<?php

declare(strict_types=1);

use App\Chisel\FeatureRegistry;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/../../../chisel.php';

use App\Chisel\Installer\AiToolingPruner;
use App\Chisel\Installer\Cleanup;
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
        File::delete($this->tempDir.'/composer.json');
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

test('retained guideline files in generated app contain no references to Chisel or test:installer while maintainer files remain unchanged', function (): void {
    foreach (['AGENTS.md', 'CLAUDE.md', 'GEMINI.md'] as $maintainerFile) {
        $content = (string) file_get_contents(base_path($maintainerFile));
        expect($content)->toContain('App/Chisel feature pruning guidelines')
            ->and($content)->toContain(FeatureRegistry::class)
            ->and($content)->toContain('composer test:installer');
    }

    $appDir = sys_get_temp_dir().'/ai_guidelines_regen_test_'.bin2hex(random_bytes(6));
    mkdir($appDir, 0777, true);

    try {
        mkdir($appDir.'/.ai/guidelines', 0777, true);
        mkdir($appDir.'/bootstrap/cache', 0777, true);
        mkdir($appDir.'/routes', 0777, true);
        file_put_contents($appDir.'/routes/web.php', "<?php\n");

        foreach (File::files(base_path('.ai/guidelines')) as $file) {
            if ($file->getFilename() === 'app.chisel.blade.php') {
                continue;
            }

            copy($file->getPathname(), $appDir.'/.ai/guidelines/'.$file->getFilename());
        }

        copy(base_path('boost.json'), $appDir.'/boost.json');

        foreach (['AGENTS.md', 'CLAUDE.md', 'GEMINI.md'] as $file) {
            copy(base_path($file), $appDir.'/'.$file);
            expect((string) file_get_contents($appDir.'/'.$file))->toContain(FeatureRegistry::class);
        }

        symlink(base_path('vendor'), $appDir.'/vendor');
        copy(base_path('artisan'), $appDir.'/artisan');
        copy(base_path('bootstrap/app.php'), $appDir.'/bootstrap/app.php');
        file_put_contents($appDir.'/.env', "APP_NAME=GeneratedApp\nAPP_ENV=local\n");

        Cleanup::updateAiGuidelines($appDir);

        $forbidden = [
            'Chisel',
            'FeatureRegistry',
            'install:features',
            'composer test:installer',
        ];

        foreach (['AGENTS.md', 'CLAUDE.md', 'GEMINI.md'] as $file) {
            $updatedContent = (string) file_get_contents($appDir.'/'.$file);
            foreach ($forbidden as $token) {
                expect($updatedContent)->not->toContain(
                    $token,
                    "Expected [{$file}] not to contain [{$token}] after post-cleanup guideline regeneration."
                );
            }
        }
    } finally {
        if (is_link($appDir.'/vendor')) {
            File::delete($appDir.'/vendor');
        }

        File::deleteDirectory($appDir);
    }
});
