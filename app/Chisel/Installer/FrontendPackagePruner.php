<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Laravel\Chisel\Chisel;
use stdClass;

/**
 * Handles package.json pruning and package removal respecting NO_NODE environments.
 */
final class FrontendPackagePruner
{
    public static function skipsNode(): bool
    {
        $server = $_SERVER;

        return filter_var(
            Env::get(
                'LARAVEL_INSTALLER_NO_NODE',
                $server['LARAVEL_INSTALLER_NO_NODE'] ?? getenv('LARAVEL_INSTALLER_NO_NODE')
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

        if (! $hadAnyPackage) {
            return;
        }

        self::pruneLockfiles($directory, ...$packages);

        if (self::skipsNode()) {
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

    public static function pruneLockfiles(string $directory, string ...$packages): void
    {
        self::pruneBunLock($directory.'/bun.lock', ...$packages);
        self::prunePackageLock($directory.'/package-lock.json', ...$packages);
    }

    private static function pruneBunLock(string $path, string ...$packages): void
    {
        if (! file_exists($path)) {
            return;
        }

        $content = (string) file_get_contents($path);
        $clean = preg_replace('/,\s*([\}\]])/', '$1', $content);
        if ($clean === null) {
            return;
        }

        /** @var stdClass|null $lock */
        $lock = json_decode($clean, false);
        if (! $lock instanceof stdClass) {
            return;
        }

        if (isset($lock->workspaces)) {
            foreach ((array) $lock->workspaces as $workspace) {
                if (! $workspace instanceof stdClass) {
                    continue;
                }

                foreach (['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies'] as $depType) {
                    if (isset($workspace->{$depType}) && $workspace->{$depType} instanceof stdClass) {
                        foreach ($packages as $pkg) {
                            unset($workspace->{$depType}->{$pkg});
                        }
                    }
                }
            }
        }

        if (isset($lock->packages) && $lock->packages instanceof stdClass) {
            foreach ($packages as $pkg) {
                unset($lock->packages->{$pkg});

                if ($pkg === '@erag/lang-sync-inertia') {
                    $exclusive = [
                        '@inertiajs/vue3',
                        '@inertiajs/svelte',
                        'vue',
                        'entities',
                    ];

                    foreach ($exclusive as $ex) {
                        unset($lock->packages->{$ex});
                    }

                    foreach ((array) $lock->packages as $key => $val) {
                        if (str_starts_with($key, '@vue/')) {
                            unset($lock->packages->{$key});
                        }
                    }
                }
            }
        }

        file_put_contents(
            $path,
            json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    private static function prunePackageLock(string $path, string ...$packages): void
    {
        if (! file_exists($path)) {
            return;
        }

        /** @var array<string, mixed>|null $lock */
        $lock = json_decode((string) file_get_contents($path), true);
        if (! is_array($lock)) {
            return;
        }

        foreach ($packages as $pkg) {
            if (isset($lock['dependencies']) && is_array($lock['dependencies'])) {
                unset($lock['dependencies'][$pkg]);
            }

            if (isset($lock['packages']) && is_array($lock['packages'])) {
                unset($lock['packages']['node_modules/'.$pkg]);

                $rootPkg = $lock['packages'][''] ?? null;
                if (is_array($rootPkg)) {
                    if (isset($rootPkg['dependencies']) && is_array($rootPkg['dependencies'])) {
                        unset($rootPkg['dependencies'][$pkg]);
                    }

                    if (isset($rootPkg['devDependencies']) && is_array($rootPkg['devDependencies'])) {
                        unset($rootPkg['devDependencies'][$pkg]);
                    }

                    $lock['packages'][''] = $rootPkg;
                }
            }

            if ($pkg === '@erag/lang-sync-inertia') {
                $exclusive = [
                    '@inertiajs/vue3',
                    '@inertiajs/svelte',
                    'vue',
                    'entities',
                ];

                foreach ($exclusive as $ex) {
                    if (isset($lock['dependencies']) && is_array($lock['dependencies'])) {
                        unset($lock['dependencies'][$ex]);
                    }

                    if (isset($lock['packages']) && is_array($lock['packages'])) {
                        unset($lock['packages']['node_modules/'.$ex]);
                    }
                }

                if (isset($lock['packages']) && is_array($lock['packages'])) {
                    foreach (array_keys($lock['packages']) as $key) {
                        if (str_starts_with($key, 'node_modules/@vue/')) {
                            unset($lock['packages'][$key]);
                        }
                    }
                }
            }
        }

        file_put_contents(
            $path,
            json_encode($lock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }
}
