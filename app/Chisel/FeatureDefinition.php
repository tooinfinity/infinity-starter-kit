<?php

declare(strict_types=1);

namespace App\Chisel;

/**
 * Declarative specification for an optional or authentication feature.
 */
final readonly class FeatureDefinition
{
    /**
     * @param  list<string>  $markerFiles
     * @param  list<string>  $exclusiveFiles
     * @param  list<string>  $emptyDirectories
     * @param  list<string>  $composerPackages
     * @param  list<string>  $frontendPackages
     * @param  list<string>  $dependencies
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $sectionMarker = null,
        public array $markerFiles = [],
        public array $exclusiveFiles = [],
        public array $emptyDirectories = [],
        public array $composerPackages = [],
        public array $frontendPackages = [],
        public array $dependencies = [],
    ) {}
}
