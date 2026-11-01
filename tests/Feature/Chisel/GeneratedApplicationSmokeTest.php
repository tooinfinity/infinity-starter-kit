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
                '--preset='.$profile->presetName(),
                '--no-interaction',
            ];

            if ($profile->hasAuthorization()) {
                $command[] = '--admin-name=Smoke Admin';
                $command[] = '--admin-email=smoke-admin@example.com';
                $command[] = '--admin-password=SmokeAdminPassword123!';
            }

            $installProcess = GeneratedProjectFixture::run($tempDir, $command);
            expect($installProcess->getExitCode())->toBe(
                0,
                "Installer failed for profile [{$profile->name}]:\n".$installProcess->getOutput()."\n".$installProcess->getErrorOutput()
            );

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
