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
     * @param  list<string>  $dependencies  Keys of features required for a valid, supported starter-kit application configuration.
     * @param  list<string>  $optionalDependencies  Keys of features that this feature optionally integrates with.
     * @param  list<string>  $capabilities  Relevant high-level capabilities provided by the feature.
     * @param  list<string>  $permissions  Permissions associated with this feature.
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
        public array $optionalDependencies = [],
        public string $description = '',
        public array $capabilities = [],
        public array $permissions = [],
        public bool $default = true,
    ) {}
}
