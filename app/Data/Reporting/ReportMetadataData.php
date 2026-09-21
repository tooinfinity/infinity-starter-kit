<?php

declare(strict_types=1);

namespace App\Data\Reporting;

use Spatie\LaravelData\Data;

/* @chisel-reporting */

final class ReportMetadataData extends Data
{
    public function __construct(
        public string $identifier,
        public string $title,
        public string $description,
        public string $category,
        public string $categoryLabel,
        public string $route,
        public string $permission,
    ) {}
}

/* @end-chisel-reporting */
