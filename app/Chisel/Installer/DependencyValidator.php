<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureRegistry;
use RuntimeException;

/**
 * Validates selected features and dependency graph integrity before mutations occur.
 */
final class DependencyValidator
{
    /**
     * Validates selected features against dependency constraints.
     *
     * @param  array<string, mixed>  $answers
     * @param  array<string, FeatureDefinition|list<string>>|null  $featuresOrMap
     *
     * @throws RuntimeException
     */
    public static function validate(array $answers, ?array $featuresOrMap = null): void
    {
        $rawAuth = (array) ($answers['auth_features'] ?? []);
        $authFeatures = array_values(array_filter($rawAuth, is_string(...)));

        $rawModules = (array) ($answers['optional_modules'] ?? []);
        $optionalModules = array_values(array_filter($rawModules, is_string(...)));

        $selectedFeatures = array_values(array_unique(array_merge($authFeatures, $optionalModules)));

        /** @var array<string, FeatureDefinition|list<string>> $definitions */
        $definitions = $featuresOrMap ?? FeatureRegistry::all();

        $isLegacyMap = false;
        foreach ($definitions as $item) {
            if (! ($item instanceof FeatureDefinition)) {
                $isLegacyMap = true;
                break;
            }
        }

        if ($isLegacyMap) {
            // A partial legacy dependency map defines prerequisites for features that have them,
            // but does not declare all available features. Registered features are resolved from
            // FeatureRegistry::all() so features with zero dependencies (e.g. authorization) are
            // recognized as valid, while unknown dependency targets are strictly rejected.
            $registeredFeatures = array_keys(FeatureRegistry::all());
            foreach ($registeredFeatures as $registeredKey) {
                $definitions[$registeredKey] ??= [];
            }
        }

        // Validate graph integrity (no self-dependencies, unknown dependencies, duplicates, or cycles)
        self::validateDefinitions($definitions);

        $validKeys = array_keys($definitions);

        $unknownFeatures = array_diff($selectedFeatures, $validKeys);
        if ($unknownFeatures !== []) {
            throw new RuntimeException(sprintf(
                'Unknown feature(s) specified: %s.',
                implode(', ', $unknownFeatures),
            ));
        }

        // Build adjacency list for dependency sorting
        $graph = [];
        foreach ($definitions as $key => $item) {
            $graph[$key] = $item instanceof FeatureDefinition ? $item->dependencies : $item;
        }

        // Precompute transitive dependencies for deterministic dependency depth sorting
        $transitiveMap = [];
        foreach (array_keys($definitions) as $key) {
            $transitiveMap[$key] = self::resolveTransitiveDependencies($key, $graph);
        }

        // Sort by dependency topology (deeper transitive dependencies evaluated first),
        // with deterministic alphabetical order as tie-breaker for independent features.
        uksort($definitions, function (string $a, string $b) use ($transitiveMap): int {
            $transitiveCountDiff = count($transitiveMap[$b] ?? []) <=> count($transitiveMap[$a] ?? []);
            if ($transitiveCountDiff !== 0) {
                return $transitiveCountDiff;
            }

            return strcmp($a, $b);
        });

        foreach ($definitions as $featureKey => $item) {
            $required = $item instanceof FeatureDefinition ? $item->dependencies : $item;
            if ($required === []) {
                continue;
            }

            if (in_array($featureKey, $selectedFeatures, true)) {
                $missing = array_diff($required, $selectedFeatures);
                if ($missing !== []) {
                    throw new RuntimeException(sprintf(
                        'The "%s" feature requires the following feature(s): %s.',
                        $featureKey,
                        implode(', ', $missing),
                    ));
                }
            }
        }
    }

    /**
     * Validates the internal consistency and graph integrity of feature definitions.
     *
     * Validates strictly against registered feature keys. Dependency targets are never
     * inferred as known features merely because they were referenced.
     *
     * @param  array<string, FeatureDefinition|list<string>>  $definitions
     *
     * @throws RuntimeException
     */
    public static function validateDefinitions(array $definitions): void
    {
        $knownKeys = array_keys($definitions);

        $graph = [];

        foreach ($definitions as $featureKey => $item) {
            $required = $item instanceof FeatureDefinition ? $item->dependencies : $item;
            $optional = $item instanceof FeatureDefinition ? $item->optionalDependencies : [];

            // Check duplicate required dependencies
            $uniqueRequired = array_values(array_unique($required));
            if (count($required) !== count($uniqueRequired)) {
                $duplicates = array_diff_assoc($required, $uniqueRequired);
                $dup = reset($duplicates);

                throw new RuntimeException(sprintf(
                    'Feature "%s" declares duplicate dependency "%s".',
                    $featureKey,
                    (string) $dup,
                ));
            }

            // Check duplicate optional dependencies
            $uniqueOptional = array_values(array_unique($optional));
            if (count($optional) !== count($uniqueOptional)) {
                $duplicates = array_diff_assoc($optional, $uniqueOptional);
                $dup = reset($duplicates);

                throw new RuntimeException(sprintf(
                    'Feature "%s" declares duplicate optional dependency "%s".',
                    $featureKey,
                    (string) $dup,
                ));
            }

            // Check overlap between required and optional
            $overlap = array_intersect($required, $optional);
            if ($overlap !== []) {
                $intersect = reset($overlap);

                throw new RuntimeException(sprintf(
                    'Feature "%s" cannot declare "%s" as both a required and optional dependency.',
                    $featureKey,
                    (string) $intersect,
                ));
            }

            // Check self-dependency
            if (in_array($featureKey, $required, true)) {
                throw new RuntimeException(sprintf(
                    'Feature "%s" cannot depend on itself.',
                    $featureKey,
                ));
            }

            if (in_array($featureKey, $optional, true)) {
                throw new RuntimeException(sprintf(
                    'Feature "%s" cannot optionally depend on itself.',
                    $featureKey,
                ));
            }

            // Check unknown dependencies
            $unknownRequired = array_diff($required, $knownKeys);
            if ($unknownRequired !== []) {
                $missingDep = reset($unknownRequired);

                throw new RuntimeException(sprintf(
                    'Feature "%s" declares unknown dependency "%s".',
                    $featureKey,
                    (string) $missingDep,
                ));
            }

            $unknownOptional = array_diff($optional, $knownKeys);
            if ($unknownOptional !== []) {
                $missingDep = reset($unknownOptional);

                throw new RuntimeException(sprintf(
                    'Feature "%s" declares unknown optional dependency "%s".',
                    $featureKey,
                    (string) $missingDep,
                ));
            }

            $graph[$featureKey] = $required;
        }

        self::detectCycles($graph);
    }

    /**
     * @param  array<string, list<string>>  $graph
     *
     * @throws RuntimeException
     */
    private static function detectCycles(array $graph): void
    {
        $visited = [];
        $recStack = [];

        foreach (array_keys($graph) as $node) {
            if (! isset($visited[$node])) {
                self::dfsCycle($node, $graph, $visited, $recStack, [$node]);
            }
        }
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $recStack
     * @param  list<string>  $path
     */
    private static function dfsCycle(
        string $node,
        array $graph,
        array &$visited,
        array &$recStack,
        array $path,
    ): void {
        $visited[$node] = true;
        $recStack[$node] = true;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if (isset($recStack[$neighbor])) {
                $cycleStartIndex = (int) array_search($neighbor, $path, true);
                $cyclePath = array_slice($path, $cycleStartIndex);
                $cyclePath[] = $neighbor;

                throw new RuntimeException(sprintf(
                    'Dependency cycle detected: %s.',
                    implode(' -> ', $cyclePath),
                ));
            }

            if (! isset($visited[$neighbor])) {
                $nextPath = $path;
                $nextPath[] = $neighbor;
                self::dfsCycle($neighbor, $graph, $visited, $recStack, $nextPath);
            }
        }

        unset($recStack[$node]);
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, bool>  $visited
     * @return array<string, bool>
     */
    private static function resolveTransitiveDependencies(string $node, array $graph, array $visited = []): array
    {
        $dependencies = [];
        $visited[$node] = true;

        foreach ($graph[$node] ?? [] as $dep) {
            $dependencies[$dep] = true;
            if (! isset($visited[$dep])) {
                foreach (self::resolveTransitiveDependencies($dep, $graph, $visited) as $transitive => $_) {
                    $dependencies[$transitive] = true;
                }
            }
        }

        return $dependencies;
    }
}
