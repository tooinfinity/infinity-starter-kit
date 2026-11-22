<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\AuditEvent;
use App\Http\Requests\Reporting\AuditReportRequest;
use App\Models\Activity;
use App\Models\User;
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

        $auditTrails = $result['paginated']->through(fn (Activity $audit): array => [
            'id' => $audit->id,
            'event' => $audit->event ?? '',
            'event_label' => $this->formatEventLabel($audit->event),
            'user' => $audit->causer instanceof User ? [
                'id' => $audit->causer->id,
                'name' => $audit->causer->name,
                'email' => $audit->causer->email,
            ] : null,
            'actor_name' => $audit->getProperty('actor.name') ?? ($audit->causer instanceof User ? $audit->causer->name : null),
            'actor_email' => $audit->getProperty('actor.email') ?? ($audit->causer instanceof User ? $audit->causer->email : null),
            'tags' => $audit->getProperty('tags'),
            'auditable_type' => $audit->subject_type,
            'auditable_id' => $audit->subject_id,
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

    private function formatEventLabel(?string $event): string
    {
        if ($event === null || $event === '') {
            return '';
        }

        return match ($event) {
            'updated' => __('audit.events.user_updated'),
            'created' => __('audit.events.user_created'),
            'deleted' => __('audit.events.user_deleted'),
            default => AuditEvent::tryFrom($event)?->label() ?? (string) str($event)->headline(),
        };
    }
}
