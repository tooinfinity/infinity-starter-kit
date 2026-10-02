<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use RuntimeException;

/**
 * Validates selected features against dependency constraints before mutations occur.
 */
final class DependencyValidator
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, list<string>>  $dependencyMap
     *
     * @throws RuntimeException
     */
    public static function validate(array $answers, array $dependencyMap): void
    {
        $rawModules = (array) ($answers['optional_modules'] ?? []);
        $optionalModules = array_values(array_filter($rawModules, is_string(...)));

        foreach ($dependencyMap as $feature => $required) {
            if (in_array($feature, $optionalModules, true)) {
                $missing = array_diff($required, $optionalModules);
                if ($missing !== []) {
                    throw new RuntimeException(sprintf(
                        'The "%s" module requires the following module(s): %s.',
                        $feature,
                        implode(', ', $missing),
                    ));
                }
            }
        }
    }
}
