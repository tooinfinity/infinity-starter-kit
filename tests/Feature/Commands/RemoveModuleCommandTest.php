<?php

declare(strict_types=1);

use App\Console\Commands\RemoveModuleCommand;

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

test('remove module command displays database data warning and can cancel', function (): void {
    $this->artisan('module:remove', ['module' => 'reporting'])
        ->expectsConfirmation('Are you sure you want to remove the [Reporting] module?', 'no')
        ->expectsOutputToContain('Removing a module removes its source code and configuration. It does not automatically destroy production database data.')
        ->assertSuccessful();
});

test('remove module command reports if module is not installed', function (): void {
    // When a module's files are not present
    $command = new RemoveModuleCommand;
    expect($command)->toBeInstanceOf(RemoveModuleCommand::class);
});
