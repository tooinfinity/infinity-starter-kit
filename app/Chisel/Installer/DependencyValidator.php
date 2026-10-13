<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureRegistry;
use RuntimeException;

/**
 * Validates selected features against dependency constraints before mutations occur.
 */
final class DependencyValidator
{
    /**
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

        $validKeys = array_keys($definitions);

        $unknownFeatures = array_diff($selectedFeatures, $validKeys);
        if ($unknownFeatures !== []) {
            throw new RuntimeException(sprintf(
                'Unknown feature(s) specified: %s.',
                implode(', ', $unknownFeatures),
            ));
        }

        uksort($definitions, function (string $a, string $b) use ($definitions): int {
            $depsA = $definitions[$a] instanceof FeatureDefinition ? $definitions[$a]->dependencies : $definitions[$a];
            $depsB = $definitions[$b] instanceof FeatureDefinition ? $definitions[$b]->dependencies : $definitions[$b];

            $countDiff = count($depsB) <=> count($depsA);
            if ($countDiff !== 0) {
                return $countDiff;
            }

            $order = ['audit-trails' => 1, 'user-management' => 2, 'settings' => 3];

            return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
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
}
