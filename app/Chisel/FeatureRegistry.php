<?php

declare(strict_types=1);

namespace App\Chisel;

use App\Chisel\Features\AuthFeatures;
use App\Chisel\Features\OptionalModules;
use InvalidArgumentException;

/**
 * Authoritative registry composing all starter kit features, dependencies, and installer metadata.
 */
final class FeatureRegistry
{
    /**
     * @return array<string, FeatureDefinition>
     */
    public static function authFeatures(): array
    {
        return AuthFeatures::all();
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function optionalModules(): array
    {
        return OptionalModules::all();
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function all(): array
    {
        return array_merge(self::authFeatures(), self::optionalModules());
    }

    public static function get(string $key): FeatureDefinition
    {
        $all = self::all();

        if (! isset($all[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown feature key [%s].', $key));
        }

        return $all[$key];
    }

    /**
     * @return list<string>
     */
    public static function allComposerPackages(): array
    {
        $packages = ['laravel/chisel', 'spatie/laravel-data'];
        foreach (self::all() as $feature) {
            foreach ($feature->composerPackages as $pkg) {
                $packages[] = $pkg;
            }
        }

        return array_values(array_unique($packages));
    }

    /**
     * @return list<string>
     */
    public static function allFrontendPackages(): array
    {
        $packages = [];
        foreach (self::all() as $feature) {
            foreach ($feature->frontendPackages as $pkg) {
                $packages[] = $pkg;
            }
        }

        return array_values(array_unique($packages));
    }

    /**
     * Transform registry into the exact shape expected by chisel-paths.php and backward-compatible tests.
     *
     * @return array<string, mixed>
     */
    public static function toPathsArray(): array
    {
        return LegacyPathsAdapter::toArray();
    }
}
