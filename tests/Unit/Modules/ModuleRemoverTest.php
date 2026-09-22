<?php

declare(strict_types=1);

use App\Enums\Module;
use App\Support\Chisel\ModuleRemover;
use Laravel\Chisel\Chisel;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/module-remover-test-'.uniqid();
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (property_exists($this, 'tempDir') && is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($this->tempDir);
    }
});

test('pruneComposerPackages returns early when composer.json does not exist', function (): void {
    ModuleRemover::pruneComposerPackages($this->tempDir, ['some/package']);
    expect(file_exists($this->tempDir.'/composer.json'))->toBeFalse();
});

test('pruneComposerPackages removes packages from require and require-dev', function (): void {
    $composer = [
        'require' => [
            'keep/pkg' => '^1.0',
            'remove/prod' => '^2.0',
        ],
        'require-dev' => [
            'keep/dev-pkg' => '^1.0',
            'remove/dev' => '^3.0',
        ],
    ];

    file_put_contents(
        $this->tempDir.'/composer.json',
        json_encode($composer, JSON_PRETTY_PRINT),
    );

    ModuleRemover::pruneComposerPackages($this->tempDir, [
        'remove/prod',
        'remove/dev',
        'nonexistent/pkg',
    ]);

    $updated = json_decode((string) file_get_contents($this->tempDir.'/composer.json'), true);

    expect($updated['require'])->toBe(['keep/pkg' => '^1.0'])
        ->and($updated['require-dev'])->toBe(['keep/dev-pkg' => '^1.0']);
});

test('pruneNpmPackages returns early when package.json does not exist', function (): void {
    ModuleRemover::pruneNpmPackages($this->tempDir, ['some-pkg']);
    expect(file_exists($this->tempDir.'/package.json'))->toBeFalse();
});

test('pruneNpmPackages removes packages from dependencies and devDependencies', function (): void {
    $package = [
        'dependencies' => [
            'keep-dep' => '^1.0',
            'remove-dep' => '^2.0',
        ],
        'devDependencies' => [
            'keep-dev-dep' => '^1.0',
            'remove-dev-dep' => '^3.0',
        ],
    ];

    file_put_contents(
        $this->tempDir.'/package.json',
        json_encode($package, JSON_PRETTY_PRINT),
    );

    ModuleRemover::pruneNpmPackages($this->tempDir, [
        'remove-dep',
        'remove-dev-dep',
        'nonexistent-dep',
    ]);

    $updated = json_decode((string) file_get_contents($this->tempDir.'/package.json'), true);

    expect($updated['dependencies'])->toBe(['keep-dep' => '^1.0'])
        ->and($updated['devDependencies'])->toBe(['keep-dev-dep' => '^1.0']);
});

test('stripMarkers skips when files do not exist', function (): void {
    $chisel = Chisel::in($this->tempDir);
    ModuleRemover::stripMarkers(Module::Reporting, $chisel, $this->tempDir);
    expect(true)->toBeTrue();
});

test('stripMarkers removes section markers from existing shared files', function (): void {
    mkdir($this->tempDir.'/routes', 0777, true);
    file_put_contents($this->tempDir.'/routes/web.php', <<<'PHP'
<?php
/* @chisel-settings */
Route::get('settings', fn () => 'settings');
/* @end-chisel-settings */
PHP);

    $chisel = Chisel::in($this->tempDir);
    ModuleRemover::stripMarkers(Module::Settings, $chisel, $this->tempDir);

    $content = (string) file_get_contents($this->tempDir.'/routes/web.php');
    expect($content)->not->toContain('@chisel-settings')
        ->and($content)->toContain("Route::get('settings', fn () => 'settings');");
});

test('remove handles Notifications AST removal from User model', function (): void {
    mkdir($this->tempDir.'/app/Models', 0777, true);
    file_put_contents($this->tempDir.'/app/Models/User.php', <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements HasLocalePreference
{
}
PHP);

    $chisel = Chisel::in($this->tempDir);
    ModuleRemover::remove(Module::Notifications, $chisel, $this->tempDir, []);

    $content = (string) file_get_contents($this->tempDir.'/app/Models/User.php');
    expect($content)->not->toContain('HasLocalePreference');
});
