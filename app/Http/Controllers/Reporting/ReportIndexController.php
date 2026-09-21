<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Data\Reporting\ReportMetadataData;
use App\Enums\ReportType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/* @chisel-reporting */

final readonly class ReportIndexController
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $reports = array_values(array_filter(
            array_map(
                fn (ReportType $type): ReportMetadataData => new ReportMetadataData(
                    identifier: $type->value,
                    title: $type->label(),
                    description: $type->description(),
                    category: $type->category()->value,
                    categoryLabel: $type->category()->label(),
                    route: $type->route(),
                    permission: $type->permission(),
                ),
                ReportType::cases(),
            ),
            fn (ReportMetadataData $report): bool => $user?->can($report->permission) ?? false,
        ));

        return Inertia::render('reports/index', [
            'reports' => $reports,
        ]);
    }
}

/* @end-chisel-reporting */
