<?php

declare(strict_types=1);

use App\Enums\Module;
use App\Support\Chisel\ModuleRemover;
use Laravel\Chisel\Chisel;

beforeEach(function (): void {
    $this->fixtureDir = sys_get_temp_dir().'/chisel-fixture-'.uniqid();
    mkdir($this->fixtureDir, 0777, true);
    mkdir($this->fixtureDir.'/routes', 0777, true);
    mkdir($this->fixtureDir.'/app/Models', 0777, true);
    mkdir($this->fixtureDir.'/app/Enums', 0777, true);
    mkdir($this->fixtureDir.'/app/Data/Reporting', 0777, true);
    mkdir($this->fixtureDir.'/config', 0777, true);

    file_put_contents($this->fixtureDir.'/composer.json', json_encode([
        'require' => [
            'php' => '^8.5.0',
            'laravel/framework' => '^13.0',
            'spatie/laravel-permission' => '^8.3',
            'spatie/laravel-data' => '^4.23',
            'erag/laravel-lang-sync-inertia' => '^2.4',
        ],
    ], JSON_PRETTY_PRINT));

    file_put_contents($this->fixtureDir.'/package.json', json_encode([
        'dependencies' => [
            'react' => '^19.0',
            '@erag/lang-sync-inertia' => '^3.1.0',
        ],
    ], JSON_PRETTY_PRINT));

    file_put_contents($this->fixtureDir.'/routes/web.php', <<<'PHP'
<?php
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => 'home');

/* @chisel-reporting */
Route::get('reports', fn () => 'reports');
/* @end-chisel-reporting */

/* @chisel-settings */
Route::get('settings', fn () => 'settings');
/* @end-chisel-settings */
PHP);

    file_put_contents($this->fixtureDir.'/app/Models/User.php', <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference
{
    use HasRoles;
}
PHP);

    file_put_contents($this->fixtureDir.'/app/Enums/Permission.php', <<<'PHP'
<?php
namespace App\Enums;

enum Permission: string
{
    case AuthorizationManage = 'authorization.manage';

    /* @chisel-reporting */
    case ReportsView = 'reports.view';
    /* @end-chisel-reporting */
}
PHP);

    file_put_contents($this->fixtureDir.'/app/Data/Reporting/ReportSummaryCardData.php', '<?php');
    file_put_contents($this->fixtureDir.'/config/permission.php', '<?php');
});

afterEach(function (): void {
    if (isset($this->fixtureDir) && is_dir($this->fixtureDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->fixtureDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($this->fixtureDir);
    }
});

test('removing Reporting removes owned files and sections without removing shared packages', function (): void {
    $chisel = Chisel::in($this->fixtureDir);
    $remaining = [Module::Authorization, Module::Settings, Module::UserManagement];

    ModuleRemover::remove(Module::Reporting, $chisel, $this->fixtureDir, $remaining);

    // Owned file removed
    expect(file_exists($this->fixtureDir.'/app/Data/Reporting/ReportSummaryCardData.php'))->toBeFalse();

    // Section removed from routes
    $routes = file_get_contents($this->fixtureDir.'/routes/web.php');
    expect($routes)->not->toContain('reports')
        ->and($routes)->toContain('settings');

    // spatie/laravel-data retained because UserManagement is in remaining modules
    $composer = json_decode(file_get_contents($this->fixtureDir.'/composer.json'), true);
    expect($composer['require'])->toHaveKey('spatie/laravel-data');
});

test('removing both Reporting and UserManagement prunes spatie/laravel-data', function (): void {
    $chisel = Chisel::in($this->fixtureDir);
    $remaining = [Module::Authorization, Module::Settings];

    ModuleRemover::remove(Module::Reporting, $chisel, $this->fixtureDir, [Module::Authorization, Module::Settings, Module::UserManagement]);
    ModuleRemover::remove(Module::UserManagement, $chisel, $this->fixtureDir, $remaining);

    $composer = json_decode(file_get_contents($this->fixtureDir.'/composer.json'), true);
    expect($composer['require'])->not->toHaveKey('spatie/laravel-data');
});

test('removing Authorization removes HasRoles trait and prunes spatie/laravel-permission', function (): void {
    $chisel = Chisel::in($this->fixtureDir);
    $remaining = [Module::Settings];

    ModuleRemover::remove(Module::Authorization, $chisel, $this->fixtureDir, $remaining);

    expect(file_exists($this->fixtureDir.'/config/permission.php'))->toBeFalse();

    $userContent = file_get_contents($this->fixtureDir.'/app/Models/User.php');
    expect($userContent)->not->toContain('HasRoles');

    $composer = json_decode(file_get_contents($this->fixtureDir.'/composer.json'), true);
    expect($composer['require'])->not->toHaveKey('spatie/laravel-permission');
});

test('removing Localization prunes erag packages from composer and npm', function (): void {
    $chisel = Chisel::in($this->fixtureDir);
    $remaining = [Module::Settings];

    ModuleRemover::remove(Module::Localization, $chisel, $this->fixtureDir, $remaining);

    $composer = json_decode(file_get_contents($this->fixtureDir.'/composer.json'), true);
    expect($composer['require'])->not->toHaveKey('erag/laravel-lang-sync-inertia');

    $packageJson = json_decode(file_get_contents($this->fixtureDir.'/package.json'), true);
    expect($packageJson['dependencies'])->not->toHaveKey('@erag/lang-sync-inertia');
});

test('removing Notifications removes HasLocalePreference interface from User model', function (): void {
    $chisel = Chisel::in($this->fixtureDir);
    $remaining = [Module::Localization];

    ModuleRemover::remove(Module::Notifications, $chisel, $this->fixtureDir, $remaining);

    $userContent = file_get_contents($this->fixtureDir.'/app/Models/User.php');
    expect($userContent)->not->toContain('HasLocalePreference');
});

test('stripMarkers removes section markers while preserving the code', function (): void {
    $chisel = Chisel::in($this->fixtureDir);

    ModuleRemover::stripMarkers(Module::Reporting, $chisel, $this->fixtureDir);

    $routes = file_get_contents($this->fixtureDir.'/routes/web.php');
    expect($routes)->toContain("Route::get('reports', fn () => 'reports');")
        ->and($routes)->not->toContain('@chisel-reporting')
        ->and($routes)->not->toContain('@end-chisel-reporting');
});
