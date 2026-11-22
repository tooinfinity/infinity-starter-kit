<?php

declare(strict_types=1);

namespace App\Http\Controllers\AuditTrails;

use App\Enums\AuditEvent;
use App\Http\Requests\AuditTrails\AuditTrailIndexRequest;
use App\Models\Activity;
use App\Models\User;
use App\Queries\AuditTrails\AuditTrailListingQuery;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AuditTrailController
{
    public function index(AuditTrailIndexRequest $request, AuditTrailListingQuery $query): Response
    {
        $filters = $request->filters();
        $perPage = $request->integer('per_page', 15);

        $paginated = $query->handle($filters, $perPage);

        $auditTrails = $paginated->through(fn (Activity $audit): array => [
            'id' => $audit->id,
            'user_id' => $audit->causer_id,
            'user' => $audit->causer instanceof User ? [
                'id' => $audit->causer->id,
                'name' => $audit->causer->name,
                'email' => $audit->causer->email,
            ] : null,
            'actor_name' => $audit->getProperty('actor.name') ?? ($audit->causer instanceof User ? $audit->causer->name : null),
            'actor_email' => $audit->getProperty('actor.email') ?? ($audit->causer instanceof User ? $audit->causer->email : null),
            'event' => $audit->event ?? '',
            'event_label' => $this->formatEventLabel($audit->event),
            'auditable_type' => $audit->subject_type,
            'auditable_id' => $audit->subject_id,
            'old_values' => $audit->attribute_changes?->get('old'),
            'new_values' => $audit->attribute_changes?->get('attributes'),
            'url' => $audit->getProperty('request.url'),
            'ip_address' => $audit->ip_address,
            'user_agent' => $audit->user_agent,
            'tags' => $audit->getProperty('tags'),
            'created_at' => $audit->created_at?->toISOString(),
        ]);

        $availableEvents = array_map(
            fn (AuditEvent $event): array => [
                'value' => $event->value,
                'label' => $event->label(),
            ],
            AuditEvent::cases(),
        );

        return Inertia::render('audit-trails/index', [
            'auditTrails' => $auditTrails,
            'filters' => $filters,
            'availableEvents' => $availableEvents,
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
