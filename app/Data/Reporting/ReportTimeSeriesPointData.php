<?php

declare(strict_types=1);

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

final class ReportTimeSeriesPointData extends Data
{
    public function __construct(
        public string $date,
        public int|float $value,
    ) {}
}
