<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureDefinition;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Script;

/**
 * Handles applying section markers for selected features and deleting exclusive files/packages when unselected.
 */
final class FeaturePruner
{
    /**
     * Register selected and unselected Chisel callbacks for a group of features.
     *
     * @param  array<string, FeatureDefinition>  $features
     * @param  array<string, callable(Chisel): void>  $beforePrune
     * @param  array<string, callable(Chisel): void>  $afterPrune
     */
    public static function register(
        Script $script,
        string $questionKey,
        array $features,
        string $directory,
        array $beforePrune = [],
        array $afterPrune = [],
    ): void {
        foreach ($features as $feature) {
            $key = $feature->key;
            $script->selected(
                $questionKey,
                $key,
                then: static function (Chisel $chisel) use ($feature): void {
                    self::applySelected($chisel, $feature);
                },
                else: static function (Chisel $chisel) use ($directory, $feature, $key, $beforePrune, $afterPrune): void {
                    if (isset($beforePrune[$key])) {
                        $beforePrune[$key]($chisel);
                    }

                    self::pruneUnselected($directory, $chisel, $feature);

                    if (isset($afterPrune[$key])) {
                        $afterPrune[$key]($chisel);
                    }
                },
            );
        }
    }

    public static function applySelected(Chisel $chisel, FeatureDefinition $feature): void
    {
        if ($feature->markerFiles !== [] && $feature->sectionMarker !== null) {
            $chisel->files(...$feature->markerFiles)->removeSectionMarkers($feature->sectionMarker);
        }
    }

    public static function pruneUnselected(string $directory, Chisel $chisel, FeatureDefinition $feature): void
    {
        if ($feature->markerFiles !== [] && $feature->sectionMarker !== null) {
            $chisel->files(...$feature->markerFiles)->removeSection($feature->sectionMarker);
        }

        if ($feature->exclusiveFiles !== []) {
            $chisel->files(...$feature->exclusiveFiles)->delete();
        }

        if ($feature->composerPackages !== []) {
            ComposerManifestPruner::removePackages($directory, ...$feature->composerPackages);
        }

        if ($feature->frontendPackages !== []) {
            FrontendPackagePruner::removePackages($directory, $chisel, ...$feature->frontendPackages);
        }

        if ($feature->emptyDirectories !== []) {
            DirectoryPruner::prune($directory, $feature->emptyDirectories);
        }
    }
}
