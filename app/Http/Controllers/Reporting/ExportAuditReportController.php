<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Requests\Reporting\ExportReportRequest;
use App\Queries\Reporting\ExportAuditReportStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* @chisel-reporting */

final readonly class ExportAuditReportController
{
    public function __invoke(ExportReportRequest $request, ExportAuditReportStream $stream): StreamedResponse
    {
        return $stream->handle([
            'date_from' => $request->filled('date_from') ? $request->string('date_from')->value() : null,
            'date_to' => $request->filled('date_to') ? $request->string('date_to')->value() : null,
            'event' => $request->filled('event') ? $request->string('event')->value() : null,
            'user_id' => $request->filled('user_id') ? $request->string('user_id')->value() : null,
            'search' => $request->filled('search') ? $request->string('search')->value() : null,
        ]);
    }
}

/* @end-chisel-reporting */
