<?php

declare(strict_types=1);

namespace App\Actions\AuditTrails;

use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/* @chisel-audit-trails */

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
    ): AuditTrail {
        $request = $this->request ?? (app()->bound('request') ? request() : null);
        $actor = $user ?? (auth()->user() instanceof User ? auth()->user() : null);

        $redactedOld = $oldValues !== null ? $this->redact($oldValues) : null;
        $redactedNew = $newValues !== null ? $this->redact($newValues) : null;

        $type = $auditable?->getMorphClass() ?? $auditableType;
        $key = $auditable?->getKey();
        $id = is_string($key) || is_int($key) ? (string) $key : $auditableId;

        /** @var AuditTrail $record */
        $record = AuditTrail::query()->create([
            'user_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => $redactedOld,
            'new_values' => $redactedNew,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'tags' => $tags,
            'created_at' => now(),
        ]);

        return $record;
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

/* @end-chisel-audit-trails */
