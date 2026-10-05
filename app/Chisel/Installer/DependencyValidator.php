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
        $rawModules = (array) ($answers['optional_modules'] ?? []);
        $optionalModules = array_values(array_filter($rawModules, is_string(...)));

        /** @var array<string, FeatureDefinition|list<string>> $definitions */
        $definitions = $featuresOrMap ?? FeatureRegistry::all();

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

            if (in_array($featureKey, $optionalModules, true)) {
                $missing = array_diff($required, $optionalModules);
                if ($missing !== []) {
                    throw new RuntimeException(sprintf(
                        'The "%s" module requires the following module(s): %s.',
                        $featureKey,
                        implode(', ', $missing),
                    ));
                }
            }
        }
    }
}
