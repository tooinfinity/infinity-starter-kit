<?php

declare(strict_types=1);

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

final class ReportBreakdownItemData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public int $count,
        public float $percentage,
    ) {}
}
