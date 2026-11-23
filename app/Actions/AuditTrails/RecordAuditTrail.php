<?php

declare(strict_types=1);

namespace App\Actions\AuditTrails;

use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Throwable;

final readonly class RecordAuditTrail
{
    /**
     * @var list<string>
     */
    private const array REDACTED_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'token',
        'secret',
        'api_key',
        'private_key',
    ];

    public function __construct(
        private ?Request $request = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  list<string>|null  $tags
     */
    public function handle(
        AuditEvent $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null,
        ?array $tags = null,
        ?string $auditableType = null,
        ?string $auditableId = null,
        ?string $url = null,
        ?string $description = null,
    ): ?Activity {
        $request = $this->request ?? (app()->bound('request') ? request() : null);

        $actor = $user;
        if (! $actor instanceof User && app()->bound('auth')) {
            try {
                $authUser = auth()->user();
                if ($authUser instanceof User) {
                    $actor = $authUser;
                }
            } catch (Throwable) {
                // Ignore auth resolution failures outside HTTP context.
            }
        }

        $redactedOld = $oldValues !== null ? $this->redact($oldValues) : null;
        $redactedNew = $newValues !== null ? $this->redact($newValues) : null;

        $type = $auditable?->getMorphClass() ?? $auditableType;
        $key = $auditable?->getKey();
        $id = is_string($key) || is_int($key) ? (string) $key : $auditableId;
        $resolvedUrl = $url ?? $request?->fullUrl();

        $activityLogger = activity(config()->string('activitylog.default_log_name', 'default'))
            ->event($event->value);

        if ($auditable instanceof Model) {
            $activityLogger->performedOn($auditable);
        }

        if ($actor instanceof User) {
            $activityLogger->causedBy($actor);
        } else {
            $activityLogger->causedByAnonymous();
        }

        $changes = [];
        if ($redactedOld !== null) {
            $changes['old'] = $redactedOld;
        }

        if ($redactedNew !== null) {
            $changes['attributes'] = $redactedNew;
        }

        $activityLogger->withChanges($changes);

        $properties = [
            'actor' => [
                'name' => $actor?->name,
                'email' => $actor?->email,
            ],
            'request' => [
                'url' => $resolvedUrl,
            ],
            'tags' => $tags ?? [],
        ];
        $activityLogger->withProperties($properties);

        $activityLogger->tap(function (ActivityContract $activity) use ($request, $type, $id): void {
            if ($activity instanceof Activity) {
                $activity->ip_address = $request?->ip();
                $activity->user_agent = $request?->userAgent();

                if ($activity->subject_type === null && $type !== null) {
                    $activity->subject_type = $type;
                    $activity->subject_id = $id;
                }
            }
        });

        /** @var Activity|null $activityRecord */
        $activityRecord = $activityLogger->log($description ?? $event->label());

        if ($activityRecord === null) {
            return null;
        }

        // Maintain legacy AuditTrail record to preserve retrieval, reporting, and anonymization
        // until PR #3 (query migration) and PR #4 (legacy removal).
        AuditTrail::query()->create([
            'id' => $activityRecord->id,
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => $redactedOld,
            'new_values' => $redactedNew,
            'url' => $resolvedUrl,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'tags' => $tags,
            'created_at' => $activityRecord->created_at ?? now(),
        ]);

        return $activityRecord;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if (
                in_array($normalizedKey, self::REDACTED_FIELDS, true)
                || str_ends_with($normalizedKey, '_secret')
                || str_ends_with($normalizedKey, '_token')
            ) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $result[$key] = $this->redact($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
