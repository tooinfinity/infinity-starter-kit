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
        );

        if (isset($composerData['scripts']['post-create-project-cmd']) && is_array($composerData['scripts']['post-create-project-cmd'])) {
            $composerData['scripts']['post-create-project-cmd'] = array_values(array_filter(
                $composerData['scripts']['post-create-project-cmd'],
                fn (mixed $cmd): bool => is_string($cmd) && ! str_contains($cmd, 'install:features'),
            ));
        }

        file_put_contents(
            $composerJsonPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }
}
