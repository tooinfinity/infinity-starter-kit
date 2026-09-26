<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Data\Reporting\ReportMetadataData;
use App\Enums\Permission;
use App\Enums\ReportType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
                ReportType::availableCases(),
            ),
            fn (ReportMetadataData $report): bool => enum_exists(Permission::class)
                ? ($user?->can($report->permission) ?? false)
                : $user !== null,
        ));

        return Inertia::render('reports/index', [
            'reports' => $reports,
        ]);
    }
}
