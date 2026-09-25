<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Requests\Reporting\ExportReportRequest;
use App\Queries\Reporting\ExportUserReportStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ExportUserReportController
{
    public function __invoke(ExportReportRequest $request, ExportUserReportStream $stream): StreamedResponse
    {
        return $stream->handle([
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->value() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->value() : null,
            'status' => $request->filled('status') ? $request->string('status')->value() : null,
            'search' => $request->filled('search') ? $request->string('search')->value() : null,
        ]);
    }
}
