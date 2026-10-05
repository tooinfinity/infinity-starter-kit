<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Laravel\Chisel\Chisel;

/**
 * Handles package.json pruning and package removal respecting NO_NODE environments.
 */
final class FrontendPackagePruner
{
    public static function skipsNode(): bool
    {
        return filter_var(
            Env::get(
                'LARAVEL_INSTALLER_NO_NODE',
                $_SERVER['LARAVEL_INSTALLER_NO_NODE'] ?? Env::get('LARAVEL_INSTALLER_NO_NODE', getenv('LARAVEL_INSTALLER_NO_NODE'))
            ),
            FILTER_VALIDATE_BOOL,
        );
    }

    public static function removePackages(string $directory, Chisel $c, string ...$packages): void
    {
        $packageJsonPath = $directory.'/package.json';
        if (! file_exists($packageJsonPath)) {
            return;
        }

        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>, optionalDependencies?: array<string, string>, peerDependencies?: array<string, string>} $packageData */
        $packageData = json_decode((string) file_get_contents($packageJsonPath), true, 512, JSON_THROW_ON_ERROR);

        $hadAnyPackage = false;
        foreach ($packages as $package) {
            if (
                isset($packageData['dependencies'][$package])
                || isset($packageData['devDependencies'][$package])
                || isset($packageData['optionalDependencies'][$package])
                || isset($packageData['peerDependencies'][$package])
            ) {
                $hadAnyPackage = true;
            }

            unset(
                $packageData['dependencies'][$package],
                $packageData['devDependencies'][$package],
                $packageData['optionalDependencies'][$package],
                $packageData['peerDependencies'][$package],
            );
        }

        file_put_contents(
            $packageJsonPath,
            json_encode($packageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        if (! $hadAnyPackage || self::skipsNode()) {
            return;
        }

        if (class_exists(ProcessFacade::class) && ProcessFacade::getFacadeRoot() !== null) {
            ProcessFacade::path($directory)
                ->forever()
                ->run($c->npm()->packageManager()->removeCommand(...$packages))
                ->throw();

            return;
        }

        $c->npm()->remove(...$packages);
    }
}
