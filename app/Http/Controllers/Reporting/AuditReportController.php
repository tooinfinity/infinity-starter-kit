<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\AuditEvent;
use App\Http\Requests\Reporting\AuditReportRequest;
use App\Models\AuditTrail;
use App\Queries\Reporting\AuditReportQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AuditReportController
{
    public function __invoke(AuditReportRequest $request, AuditReportQuery $query): Response
    {
        $filters = $request->filters();
        $perPage = $request->integer('per_page', 15);

        $result = $query->handle($filters, $perPage);

        $auditTrails = $result['paginated']->through(fn (AuditTrail $audit): array => [
            'id' => $audit->id,
            'event' => $audit->event->value,
            'event_label' => $audit->event->label(),
            'user' => $audit->user ? [
                'id' => $audit->user->id,
                'name' => $audit->user->name,
                'email' => $audit->user->email,
            ] : null,
            'auditable_type' => $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'ip_address' => $audit->ip_address,
            'created_at' => $audit->created_at?->toISOString() ?? '',
        ]);

        $availableEvents = array_map(
            fn (AuditEvent $event): array => [
                'value' => $event->value,
                'label' => $event->label(),
            ],
            AuditEvent::cases(),
        );

        return Inertia::render('reports/audit', [
            'summary' => $result['summary'],
            'series' => $result['series'],
            'breakdown' => $result['breakdown'],
            'auditTrails' => $auditTrails,
            'availableEvents' => $availableEvents,
            'filters' => [
                'date_from' => $result['date_from'],
                'date_to' => $result['date_to'],
                'event' => $filters['event'] ?? 'all',
                'user_id' => $filters['user_id'] ?? null,
                'search' => $filters['search'] ?? null,
                'sort' => $filters['sort'] ?? 'created_at',
                'direction' => $filters['direction'] ?? 'desc',
            ],
        ]);
    }
}
