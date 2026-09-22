<?php

declare(strict_types=1);

namespace App\Support\Chisel;

use App\Enums\Module;
use App\Exceptions\CircularDependencyException;
use App\Exceptions\DependentModuleException;

final class ModuleResolver
{
    /**
     * Normalize answers from various formats (new unified question or legacy keys) into Module instances.
     *
     * @param  array<string, mixed>|list<string|Module>  $input
     * @return list<Module>
     */
    public static function normalize(array $input): array
    {
        $modules = [];

        // Check if input is an associative answers array
        if (array_key_exists('optional_modules', $input)) {
            $raw = (array) $input['optional_modules'];

            foreach ($raw as $val) {
                if ($module = self::findModule($val)) {
                    $modules[$module->value] = $module;
                }
            }
        }

        // Support legacy authorization_features
        if (array_key_exists('authorization_features', $input)) {
            $rawAuth = (array) $input['authorization_features'];
            if (in_array('roles-permissions', $rawAuth, true) || in_array('authorization', $rawAuth, true)) {
                $modules[Module::Authorization->value] = Module::Authorization;
            }
        }

        // Support legacy application_features
        if (array_key_exists('application_features', $input)) {
            $rawApp = (array) $input['application_features'];
            foreach ($rawApp as $val) {
                if ($module = self::findModule($val)) {
                    $modules[$module->value] = $module;
                }
            }
        }

        // Direct list of values or Module instances
        if (! array_key_exists('optional_modules', $input)
            && ! array_key_exists('authorization_features', $input)
            && ! array_key_exists('application_features', $input)) {
            foreach ($input as $val) {
                if ($module = self::findModule($val)) {
                    $modules[$module->value] = $module;
                }
            }
        }

        return array_values($modules);
    }

    /**
     * Resolve selected modules, automatically including mandatory dependencies,
     * verifying acyclicity, and returning them in topological order.
     *
     * @param  list<Module|string>|array<string, mixed>  $selected
     * @return list<Module>
     *
     * @throws CircularDependencyException
     */
    public static function resolve(array $selected): array
    {
        $initial = self::normalize($selected);
        $resolved = [];

        // Expand all dependencies recursively
        $toProcess = $initial;
        while ($toProcess !== []) {
            $current = array_shift($toProcess);
            if (! isset($resolved[$current->value])) {
                $resolved[$current->value] = $current;
                foreach ($current->dependencies() as $dependency) {
                    if (! isset($resolved[$dependency->value])) {
                        $toProcess[] = $dependency;
                    }
                }
            }
        }

        return self::topologicalSort(array_values($resolved));
    }

    /**
     * Returns unselected modules in reverse topological order (dependents first).
     *
     * @param  list<Module|string>|array<string, mixed>  $selected
     * @return list<Module>
     */
    public static function unselected(array $selected): array
    {
        $resolved = self::resolve($selected);
        $resolvedKeys = array_map(fn (Module $m): string => $m->value, $resolved);

        $unselected = [];
        foreach (Module::cases() as $case) {
            if (! in_array($case->value, $resolvedKeys, true)) {
                $unselected[] = $case;
            }
        }

        // Sort unselected in reverse topological order so dependents are removed before dependencies
        $allSorted = self::topologicalSort(Module::cases());
        $reversed = array_reverse($allSorted);

        return array_values(array_filter(
            $reversed,
            fn (Module $m): bool => in_array($m, $unselected, true),
        ));
    }

    /**
     * Validate reverse dependencies before removal.
     * Throws an exception if any installed module depends on the module to be removed.
     *
     * @param  list<Module|string>  $installedModules
     *
     * @throws DependentModuleException
     */
    public static function validateRemoval(Module $toRemove, array $installedModules): void
    {
        $installed = self::normalize($installedModules);
        $dependents = [];

        foreach ($installed as $module) {
            if ($module === $toRemove) {
                continue;
            }

            if (self::hasDependency($module, $toRemove)) {
                $dependents[] = $module->label();
            }
        }

        if ($dependents !== []) {
            $dependentsString = implode(' and ', $dependents);
            $verb = count($dependents) > 1 ? 'depend' : 'depends';

            throw new DependentModuleException(
                "Cannot remove {$toRemove->label()} because {$dependentsString} {$verb} on it. Remove {$dependentsString} first."
            );
        }
    }

    /**
     * Check if a module depends on target directly or indirectly.
     */
    public static function hasDependency(Module $source, Module $target): bool
    {
        $visited = [];
        $queue = $source->dependencies();

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === $target) {
                return true;
            }

            if (! in_array($current->value, $visited, true)) {
                $visited[] = $current->value;
                foreach ($current->dependencies() as $next) {
                    $queue[] = $next;
                }
            }
        }

        return false;
    }

    /**
     * Calculate exclusive Composer packages owned by modules to remove that are NOT needed by remaining modules.
     *
     * @param  list<Module>  $modulesToRemove
     * @param  list<Module>  $remainingModules
     * @return list<string>
     */
    public static function exclusiveComposerPackages(array $modulesToRemove, array $remainingModules): array
    {
        $packagesToRemove = [];
        foreach ($modulesToRemove as $module) {
            foreach ($module->composerPackages() as $package) {
                $packagesToRemove[$package] = $package;
            }
        }

        $retainedPackages = [];
        foreach ($remainingModules as $module) {
            foreach ($module->composerPackages() as $package) {
                $retainedPackages[$package] = true;
            }
        }

        return array_values(array_diff_key($packagesToRemove, $retainedPackages));
    }

    /**
     * Calculate exclusive NPM packages owned by modules to remove that are NOT needed by remaining modules.
     *
     * @param  list<Module>  $modulesToRemove
     * @param  list<Module>  $remainingModules
     * @return list<string>
     */
    public static function exclusiveNpmPackages(array $modulesToRemove, array $remainingModules): array
    {
        $packagesToRemove = [];
        foreach ($modulesToRemove as $module) {
            foreach ($module->npmPackages() as $package) {
                $packagesToRemove[$package] = $package;
            }
        }

        $retainedPackages = [];
        foreach ($remainingModules as $module) {
            foreach ($module->npmPackages() as $package) {
                $retainedPackages[$package] = true;
            }
        }

        return array_values(array_diff_key($packagesToRemove, $retainedPackages));
    }

    /**
     * Perform topological sort on modules with cycle detection.
     *
     * @param  list<Module>  $modules
     * @return list<Module>
     *
     * @throws CircularDependencyException
     */
    private static function topologicalSort(array $modules): array
    {
        $moduleMap = [];
        foreach ($modules as $m) {
            $moduleMap[$m->value] = $m;
        }

        $visited = [];
        $visiting = [];
        $sorted = [];

        $visit = function (Module $module) use (&$visit, &$visited, &$visiting, &$sorted, $moduleMap): void {
            if (isset($visited[$module->value])) {
                return;
            }

            if (isset($visiting[$module->value])) {
                throw new CircularDependencyException(
                    "Circular dependency detected involving module [{$module->value}]."
                );
            }

            $visiting[$module->value] = true;

            foreach ($module->dependencies() as $dependency) {
                // If dependency is part of the graph being sorted
                if (isset($moduleMap[$dependency->value])) {
                    $visit($moduleMap[$dependency->value]);
                }
            }

            unset($visiting[$module->value]);
            $visited[$module->value] = true;
            $sorted[] = $module;
        };

        foreach ($modules as $module) {
            $visit($module);
        }

        return $sorted;
    }

    /**
     * Find a Module case by string or enum.
     */
    private static function findModule(mixed $value): ?Module
    {
        if ($value instanceof Module) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        // Support alias
        if ($value === 'roles-permissions') {
            return Module::Authorization;
        }

        return Module::tryFrom($value);
    }
}
