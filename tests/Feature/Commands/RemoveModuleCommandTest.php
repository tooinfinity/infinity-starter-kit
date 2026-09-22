<?php

declare(strict_types=1);

use App\Enums\Module;

test('remove module command fails with invalid module name', function (): void {
    $this->artisan('module:remove', ['module' => 'invalid-module', '--force' => true])
        ->assertFailed()
        ->expectsOutputToContain('Invalid module [invalid-module]. Available modules:');
});

test('remove module command rejects removing module when dependents are installed', function (): void {
    $this->artisan('module:remove', ['module' => 'audit-trails', '--force' => true])
        ->assertFailed()
        ->expectsOutputToContain('Cannot remove Audit Trails because Reporting depends on it. Remove Reporting first.');
});

test('remove module command supports roles-permissions alias', function (): void {
    $this->artisan('module:remove', ['module' => 'roles-permissions', '--force' => true])
        ->assertFailed()
        ->expectsOutputToContain('Cannot remove Authorization because');
});

test('remove module command displays database data warning and can cancel', function (): void {
    $this->artisan('module:remove', ['module' => 'reporting'])
        ->expectsConfirmation('Are you sure you want to remove the [Reporting] module?', 'no')
        ->expectsOutputToContain('Removing a module removes its source code and configuration. It does not automatically destroy production database data.')
        ->expectsOutputToContain('Operation cancelled.')
        ->assertSuccessful();
});

test('remove module command reports if module is not installed', function (): void {
    $tempDir = sys_get_temp_dir().'/uninstalled-test-'.uniqid();
    mkdir($tempDir, 0777, true);

    $originalBasePath = app()->basePath();
    app()->setBasePath($tempDir);

    try {
        $this->artisan('module:remove', ['module' => 'reporting', '--force' => true])
            ->expectsOutputToContain('Module [Reporting] is not installed.')
            ->assertSuccessful();
    } finally {
        app()->setBasePath($originalBasePath);
        rmdir($tempDir);
    }
});

test('remove module command successfully removes module when confirmed', function (): void {
    $tempDir = sys_get_temp_dir().'/remove-test-'.uniqid();
    mkdir($tempDir.'/app/Enums', 0777, true);
    file_put_contents($tempDir.'/'.Module::Reporting->ownedFiles()[0], '<?php');
    file_put_contents($tempDir.'/composer.json', '{}');
    file_put_contents($tempDir.'/package.json', '{}');

    $originalBasePath = app()->basePath();
    app()->setBasePath($tempDir);

    try {
        $this->artisan('module:remove', ['module' => 'reporting'])
            ->expectsConfirmation('Are you sure you want to remove the [Reporting] module?', 'yes')
            ->expectsOutputToContain('Module [Reporting] successfully removed.')
            ->assertSuccessful();

        expect(file_exists($tempDir.'/'.Module::Reporting->ownedFiles()[0]))->toBeFalse();
    } finally {
        app()->setBasePath($originalBasePath);
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($tempDir);
    }
});
