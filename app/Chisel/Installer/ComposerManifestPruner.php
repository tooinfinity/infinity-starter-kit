<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

/**
 * Handles package removal and script cleanup directly within composer.json.
 */
final class ComposerManifestPruner
{
    public static function removePackages(string $directory, string ...$packages): void
    {
        $composerJsonPath = $directory.'/composer.json';
        if (! file_exists($composerJsonPath)) {
            return;
        }

        /** @var array{require?: array<string, string>, require-dev?: array<string, string>, scripts?: array<string, mixed>} $composerData */
        $composerData = json_decode((string) file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

        foreach ($packages as $package) {
            unset(
                $composerData['require'][$package],
                $composerData['require-dev'][$package],
            );
        }

        file_put_contents(
            $composerJsonPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    public static function cleanPostCreate(string $directory): void
    {
        $composerJsonPath = $directory.'/composer.json';
        if (! file_exists($composerJsonPath)) {
            return;
        }

        /** @var array{require?: array<string, string>, require-dev?: array<string, string>, scripts?: array<string, mixed>} $composerData */
        $composerData = json_decode((string) file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

        unset(
            $composerData['require']['laravel/chisel'],
            $composerData['require-dev']['laravel/chisel'],
            $composerData['scripts']['docs:generate'],
            $composerData['scripts']['docs:check'],
            $composerData['scripts']['test:installer'],
            $composerData['scripts']['test:smoke'],
        );

        if (isset($composerData['scripts']['post-create-project-cmd']) && is_array($composerData['scripts']['post-create-project-cmd'])) {
            $composerData['scripts']['post-create-project-cmd'] = array_values(array_filter(
                $composerData['scripts']['post-create-project-cmd'],
                fn (mixed $cmd): bool => is_string($cmd) && ! str_contains($cmd, 'install:features'),
            ));
        }

        if (isset($composerData['scripts']['test:lint']) && is_array($composerData['scripts']['test:lint'])) {
            $composerData['scripts']['test:lint'] = array_values(array_filter(
                $composerData['scripts']['test:lint'],
                fn (mixed $cmd): bool => is_string($cmd) && ! str_contains($cmd, 'chisel:docs'),
            ));
        }

        if (isset($composerData['scripts']['test:coverage']) && is_string($composerData['scripts']['test:coverage'])) {
            $cleanCoverage = str_replace(
                [' --exclude-group=installer-e2e --exclude-group=installer-smoke', ' --exclude-group=installer-e2e', ' --exclude-group=installer-smoke'],
                '',
                $composerData['scripts']['test:coverage'],
            );
            $composerData['scripts']['test:coverage'] = $cleanCoverage;
        }

        if (isset($composerData['scripts']['test:quick']) && is_string($composerData['scripts']['test:quick'])) {
            $cleanQuick = str_replace(
                [' --exclude-group=installer-e2e --exclude-group=installer-smoke', ' --exclude-group=installer-e2e', ' --exclude-group=installer-smoke'],
                '',
                $composerData['scripts']['test:quick'],
            );
            $composerData['scripts']['test:quick'] = $cleanQuick;
        }

        file_put_contents(
            $composerJsonPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }
}
