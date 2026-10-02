<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureDefinition;
use Laravel\Chisel\Chisel;

/**
 * Handles applying section markers for selected features and deleting exclusive files/packages when unselected.
 */
final class FeaturePruner
{
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
            ComposerSynchronizer::removePackages($directory, ...$feature->composerPackages);
        }

        if ($feature->frontendPackages !== []) {
            FrontendPackagePruner::removePackages($directory, $chisel, ...$feature->frontendPackages);
        }

        if ($feature->emptyDirectories !== []) {
            DirectoryPruner::prune($directory, $feature->emptyDirectories);
        }
    }
}
