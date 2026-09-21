<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

/* @chisel-reporting */

final readonly class ExportAuditReportStream
{
    public function __construct(
        private AuditReportQuery $reportQuery,
    ) {}

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, event?: string|null, user_id?: string|null, search?: string|null}  $filters
     */
    public function handle(array $filters = []): StreamedResponse
    {
        $dateFromStr = ! empty($filters['date_from'])
            ? $filters['date_from']
            : Date::now()->subDays(29)->toDateString();

        $dateToStr = ! empty($filters['date_to'])
            ? $filters['date_to']
            : Date::now()->toDateString();

        $from = Date::parse($dateFromStr)->startOfDay();
        $to = Date::parse($dateToStr)->endOfDay();

        /** @var Builder<AuditTrail> $query */
        $query = $this->reportQuery->buildFilteredQuery($filters, $from, $to)->latest('created_at');

        $fileName = sprintf('audit-report-%s.csv', Date::now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($query): void {
            /** @var resource $handle */
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel / Unicode compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('reports.export.columns.id'),
                __('reports.export.columns.event'),
                __('reports.export.columns.actor_name'),
                __('reports.export.columns.actor_email'),
                __('reports.export.columns.target_type'),
                __('reports.export.columns.target_id'),
                __('reports.export.columns.ip_address'),
                __('reports.export.columns.created_at'),
            ],
                escape: '\\');

            foreach ($query->cursor() as $audit) {
                /** @var AuditTrail $audit */
                $actorName = $audit->user !== null ? $audit->user->name : __('reports.common.system');
                $actorEmail = $audit->user !== null ? $audit->user->email : '';

                fputcsv($handle, [
                    $audit->id,
                    $this->sanitizeValue($audit->event->value),
                    $this->sanitizeValue($actorName),
                    $this->sanitizeValue($actorEmail),
                    $this->sanitizeValue($audit->auditable_type ?? ''),
                    $this->sanitizeValue($audit->auditable_id ?? ''),
                    $this->sanitizeValue($audit->ip_address ?? ''),
                    $audit->created_at?->toISOString() ?? '',
                ],
                    escape: '\\');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function sanitizeValue(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

/* @end-chisel-reporting */
