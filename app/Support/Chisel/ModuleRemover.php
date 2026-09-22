<?php

declare(strict_types=1);

namespace App\Support\Chisel;

use App\Enums\Module;
use Laravel\Chisel\Chisel;

final class ModuleRemover
{
    /**
     * Remove a module and its exclusive dependencies from the project.
     *
     * @param  list<Module>  $remainingModules
     */
    public static function remove(Module $module, Chisel $chisel, string $directory, array $remainingModules): void
    {
        // 1. Module-specific AST removals on User model
        if ($module === Module::Authorization) {
            $userModel = $directory.'/app/Models/User.php';
            if (file_exists($userModel)) {
                $chisel->php('app/Models/User.php')
                    ->removeImport('Spatie\\Permission\\Traits\\HasRoles')
                    ->removeTrait('HasRoles');
            }
        }

        if ($module === Module::Notifications) {
            $userModel = $directory.'/app/Models/User.php';
            if (file_exists($userModel)) {
                $chisel->php('app/Models/User.php')
                    ->removeImport('Illuminate\\Contracts\\Translation\\HasLocalePreference')
                    ->removeInterface('HasLocalePreference');
            }
        }

        // 2. Remove sections from shared files
        $sharedFiles = array_filter(
            $module->sharedFiles(),
            fn (string $path): bool => file_exists($directory.'/'.$path),
        );

        if ($sharedFiles !== []) {
            $chisel->files(...$sharedFiles)->removeSection($module->chiselTag());
        }

        // 3. Delete owned files
        $ownedFiles = array_filter(
            $module->ownedFiles(),
            fn (string $path): bool => file_exists($directory.'/'.$path),
        );

        if ($ownedFiles !== []) {
            $chisel->files(...$ownedFiles)->delete();
        }

        // 4. Prune exclusive Composer dependencies
        $exclusiveComposer = ModuleResolver::exclusiveComposerPackages([$module], $remainingModules);
        if ($exclusiveComposer !== []) {
            self::pruneComposerPackages($directory, $exclusiveComposer);
        }

        // 5. Prune exclusive NPM dependencies
        $exclusiveNpm = ModuleResolver::exclusiveNpmPackages([$module], $remainingModules);
        if ($exclusiveNpm !== []) {
            self::pruneNpmPackages($directory, $exclusiveNpm);
        }
    }

    /**
     * Strip section markers from files for a retained module.
     */
    public static function stripMarkers(Module $module, Chisel $chisel, string $directory): void
    {
        $allFiles = array_merge($module->sharedFiles(), $module->ownedFiles());

        $existing = array_values(array_filter(
            $allFiles,
            fn (string $path): bool => file_exists($directory.'/'.$path),
        ));

        if ($existing !== []) {
            $chisel->files(...$existing)->removeSectionMarkers($module->chiselTag());
        }
    }

    /**
     * Remove composer packages from composer.json.
     *
     * @param  list<string>  $packages
     */
    public static function pruneComposerPackages(string $directory, array $packages): void
    {
        $composerPath = $directory.'/composer.json';
        if (! file_exists($composerPath)) {
            return;
        }

        /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $data */
        $data = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);

        $modified = false;
        foreach ($packages as $pkg) {
            if (isset($data['require'][$pkg])) {
                unset($data['require'][$pkg]);
                $modified = true;
            }
            if (isset($data['require-dev'][$pkg])) {
                unset($data['require-dev'][$pkg]);
                $modified = true;
            }
        }

        if ($modified) {
            file_put_contents(
                $composerPath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
            );
        }
    }

    /**
     * Remove NPM packages from package.json.
     *
     * @param  list<string>  $packages
     */
    public static function pruneNpmPackages(string $directory, array $packages): void
    {
        $packagePath = $directory.'/package.json';
        if (! file_exists($packagePath)) {
            return;
        }

        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $data */
        $data = json_decode((string) file_get_contents($packagePath), true, 512, JSON_THROW_ON_ERROR);

        $modified = false;
        foreach ($packages as $pkg) {
            if (isset($data['dependencies'][$pkg])) {
                unset($data['dependencies'][$pkg]);
                $modified = true;
            }
            if (isset($data['devDependencies'][$pkg])) {
                unset($data['devDependencies'][$pkg]);
                $modified = true;
            }
        }

        if ($modified) {
            file_put_contents(
                $packagePath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
            );
        }
    }
}
