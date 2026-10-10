<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Chisel\Features\CrossFeatureTests as ProductionCrossFeatureTests;

/**
 * Backward-compatible proxy to the authoritative production cross-feature metadata.
 */
final class CrossFeatureTests
{
    /**
     * @return list<array{features: list<string>, files: list<string>}>
     */
    public static function all(): array
    {
        return ProductionCrossFeatureTests::all();
    }
}
