<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Support\Facades\Process;
use Laravel\Chisel\Chisel;

use function Laravel\Prompts\spin;

/**
 * Executes post-scaffolding toolchain steps including Pint formatting, Wayfinder generation, and frontend build pipeline.
 */
final class PostScaffoldRunner
{
    public static function run(InstallerContext $context, string $basePath): void
    {
        if (file_exists($basePath.'/vendor/bin/pint')) {
            chiselRun(['vendor/bin/pint', '--format', 'agent'], 'Format PHP Code', $basePath);
        }

        if (file_exists($basePath.'/artisan')) {
            chiselRun([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form', '--no-interaction'], 'Generate Wayfinder Resources', $basePath);
        }

        if (! $context->skipNode) {
            self::installFrontendDependencies($basePath);
            self::buildAssets($basePath);
            self::lintAssets($basePath);
        }
    }

    private static function installFrontendDependencies(string $basePath): void
    {
        $npm = Chisel::in($basePath)->npm();
        $packageManager = $npm->packageManager();

        spin(
            fn () => Process::path($basePath)->forever()->run($packageManager->installCommand())->throw(),
            "Installing dependencies with {$packageManager->value}...",
        );
    }

    private static function buildAssets(string $basePath): void
    {
        $npm = Chisel::in($basePath)->npm();
        $packageManager = $npm->packageManager();

        spin(
            fn () => Process::path($basePath)->forever()->run($packageManager->runCommand('build'))->throw(),
            'Building assets...',
        );
    }

    private static function lintAssets(string $basePath): void
    {
        $npm = Chisel::in($basePath)->npm();
        $packageManager = $npm->packageManager();

        spin(
            fn () => Process::path($basePath)->forever()->run($packageManager->runCommand('lint'))->throw(),
            'Linting frontend assets...',
        );
    }
}
