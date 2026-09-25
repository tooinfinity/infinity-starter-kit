<?php

declare(strict_types=1);

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

final class ReportSummaryCardData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public int|float|string $value,
        public string $description,
        public string $icon,
    ) {}
}
