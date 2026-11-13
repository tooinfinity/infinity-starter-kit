<?php

declare(strict_types=1);

use App\Chisel\Installer\Profile;
use Tests\Support\GeneratedApplicationSmokePipeline;
use Tests\Support\GeneratedProjectFixture;

uses()->group('installer-smoke');

describe('Generated Application Smoke Pipeline', function (): void {
    it('generates, boots, migrates, builds, and verifies representative application', function (Profile $profile): void {
        $tempDir = GeneratedProjectFixture::create();

        try {
            $command = [
                PHP_BINARY,
                'artisan',
                'install:features',
                '--answers='.json_encode($profile->answers(), JSON_THROW_ON_ERROR),
                '--no-interaction',
            ];

            $installProcess = GeneratedProjectFixture::run($tempDir, $command);
            expect($installProcess->getExitCode())->toBe(
                0,
                "Installer failed for profile [{$profile->name}]:\n".$installProcess->getOutput()."\n".$installProcess->getErrorOutput()
            );

            if ($profile->hasAuthorization()) {
                $adminProcess = GeneratedProjectFixture::run($tempDir, [
                    PHP_BINARY,
                    'artisan',
                    'authorization:create-admin',
                    '--name=Smoke Admin',
                    '--email=smoke-admin@example.com',
                    '--stdin',
                ], [], 60, "SmokeAdminPassword123!\n");

                expect($adminProcess->getExitCode())->toBe(0, $adminProcess->getOutput()."\n".$adminProcess->getErrorOutput());
            }

            GeneratedApplicationSmokePipeline::run($tempDir, $profile);
        } finally {
            GeneratedProjectFixture::remove($tempDir);
        }
    })->with([
        'minimal' => [Profile::fromName('minimal')],
        'default' => [Profile::fromName('default')],
        'full' => [Profile::fromName('full')],
    ]);
});
