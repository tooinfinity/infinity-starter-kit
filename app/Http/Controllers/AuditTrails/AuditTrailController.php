<?php

declare(strict_types=1);

namespace App\Http\Controllers\AuditTrails;

use App\Enums\AuditEvent;
use App\Http\Requests\AuditTrails\AuditTrailIndexRequest;
use App\Models\AuditTrail;
use App\Queries\AuditTrails\AuditTrailListingQuery;
use Inertia\Inertia;
use Inertia\Response;

/* @chisel-audit-trails */

final readonly class AuditTrailController
{
    public function index(AuditTrailIndexRequest $request, AuditTrailListingQuery $query): Response
    {
        $filters = $request->filters();
        $perPage = $request->integer('per_page', 15);

        $paginated = $query->handle($filters, $perPage);

        $auditTrails = $paginated->through(fn (AuditTrail $audit): array => [
            'id' => $audit->id,
            'user_id' => $audit->user_id,
            'user' => $audit->user ? [
                'id' => $audit->user->id,
                'name' => $audit->user->name,
                'email' => $audit->user->email,
            ] : null,
            'event' => $audit->event->value,
            'event_label' => $audit->event->label(),
            'auditable_type' => $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'old_values' => $audit->old_values,
            'new_values' => $audit->new_values,
            'url' => $audit->url,
            'ip_address' => $audit->ip_address,
            'user_agent' => $audit->user_agent,
            'tags' => $audit->tags,
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
}

/* @end-chisel-audit-trails */
