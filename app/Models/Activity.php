<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use App\Queries\AuditTrails\HistoricalAuditTrailQuery;
use Carbon\CarbonInterface;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * @property-read string $id
 * @property string|null $log_name
 * @property string|null $description
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $causer_type
 * @property string|null $causer_id
 * @property string|null $event
 * @property Collection<string, mixed>|null $attribute_changes
 * @property Collection<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonInterface|null $created_at
 * @property-read Model|null $causer
 * @property-read Model|null $subject
 * @property-read User|null $user
 * @property-read string|null $user_id
 * @property-read string|null $actor_name
 * @property-read string|null $actor_email
 * @property-read string|null $url
 * @property-read array<int, string>|null $tags
 * @property-read array<string, mixed>|null $old_values
 * @property-read array<string, mixed>|null $new_values
 * @property-read string|null $auditable_type
 * @property-read string|null $auditable_id
 * @property-read string $event_label
 * @property-read AuditEvent|null $audit_event
 */
#[Table(name: 'activity_log')]
#[Hidden(['properties', 'attribute_changes'])]
final class Activity extends SpatieActivity implements ActivityContract
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    use HasUuids;

    public const ?string UPDATED_AT = null;

    public static function booted(): void
    {
        self::updating(function (): never {
            throw new RuntimeException('Audit trail records are immutable and cannot be updated.');
        });

        self::deleting(function (): never {
            throw new RuntimeException('Audit trail records cannot be individually deleted. Use the retention prune command instead.');
        });
    }

    /**
     * Build an Eloquent query for Activity that combines Spatie's activity_log with
     * any historical records in legacy audit_trails that have not yet been migrated.
     *
     * TEMPORARY COMPATIBILITY MECHANISM:
     * Delegates to App\Queries\AuditTrails\HistoricalAuditTrailQuery to maintain
     * read-visibility for historical records. Can be removed in PR #4 once legacy
     * audit_trails is retired and data backfill is complete.
     *
     * @return Builder<self>
     */
    public static function withHistorical(): Builder
    {
        return HistoricalAuditTrailQuery::build();
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return array_merge(parent::casts(), [
            'id' => 'string',
            'causer_id' => 'string',
            'subject_id' => 'string',
            'ip_address' => 'string',
            'user_agent' => 'string',
            'created_at' => 'datetime',
        ]);
    }

    /**
     * @return Attribute<?User, never>
     */
    public function user(): Attribute
    {
        return Attribute::make(get: fn (): ?User => $this->causer instanceof User ? $this->causer : null);
    }

    /**
     * @return Attribute<?string, never>
     */
    public function userId(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->causer_id);
    }

    /**
     * @return Attribute<?string, never>
     */
    public function auditableType(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->subject_type);
    }

    /**
     * @return Attribute<?string, never>
     */
    public function auditableId(): Attribute
    {
        return Attribute::make(get: fn (): ?string => $this->subject_id);
    }

    /**
     * @return Attribute<string, never>
     */
    public function eventLabel(): Attribute
    {
        return Attribute::make(get: fn (): string => AuditEvent::tryFrom($this->event ?? '')?->label() ?? $this->description ?? $this->event ?? '');
    }

    /**
     * @return Attribute<?AuditEvent, never>
     */
    public function auditEvent(): Attribute
    {
        return Attribute::make(get: fn (): ?AuditEvent => AuditEvent::tryFrom($this->event ?? ''));
    }

    /**
     * @return Attribute<?string, never>
     */
    public function actorName(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            /** @var string|null $actorName */
            $actorName = $this->getProperty('actor.name');
            if ($actorName !== null && $actorName !== '') {
                return $actorName;
            }

            if (isset($this->attributes['legacy_actor_name']) && is_string($this->attributes['legacy_actor_name'])) {
                return $this->attributes['legacy_actor_name'];
            }

            if ($this->causer instanceof User) {
                return $this->causer->name;
            }

            return null;
        });
    }

    /**
     * @return Attribute<?string, never>
     */
    public function actorEmail(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            /** @var string|null $actorEmail */
            $actorEmail = $this->getProperty('actor.email');
            if ($actorEmail !== null && $actorEmail !== '') {
                return $actorEmail;
            }

            if (isset($this->attributes['legacy_actor_email']) && is_string($this->attributes['legacy_actor_email'])) {
                return $this->attributes['legacy_actor_email'];
            }

            if ($this->causer instanceof User) {
                return $this->causer->email;
            }

            return null;
        });
    }

    /**
     * @return Attribute<?string, never>
     */
    public function url(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            /** @var string|null $url */
            $url = $this->getProperty('request.url');
            if ($url !== null && $url !== '') {
                return $url;
            }

            return isset($this->attributes['legacy_url']) && is_string($this->attributes['legacy_url'])
                ? $this->attributes['legacy_url']
                : null;
        });
    }

    /**
     * @return Attribute<array<int, string>|null, never>
     */
    public function tags(): Attribute
    {
        return Attribute::make(get: function (): ?array {
            /** @var array<int, string>|null $tags */
            $tags = $this->getProperty('tags');
            if (is_array($tags)) {
                return array_values($tags);
            }

            if (isset($this->attributes['legacy_tags'])) {
                $raw = $this->attributes['legacy_tags'];
                $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

                return is_array($decoded) ? array_values(array_filter($decoded, is_string(...))) : null;
            }

            return null;
        });
    }

    /**
     * @return Attribute<array<string, mixed>|null, never>
     */
    public function oldValues(): Attribute
    {
        return Attribute::make(get: function (): ?array {
            if ($this->attribute_changes instanceof Collection && $this->attribute_changes->has('old')) {
                $old = $this->attribute_changes->get('old');

                return is_array($old) ? $old : null;
            }

            if (isset($this->attributes['legacy_old_values'])) {
                $raw = $this->attributes['legacy_old_values'];
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                return is_array($decoded) ? $decoded : null;
            }

            return null;
        });
    }

    /**
     * @return Attribute<array<string, mixed>|null, never>
     */
    public function newValues(): Attribute
    {
        return Attribute::make(get: function (): ?array {
            if ($this->attribute_changes instanceof Collection && $this->attribute_changes->has('attributes')) {
                $attrs = $this->attribute_changes->get('attributes');

                return is_array($attrs) ? $attrs : null;
            }

            if (isset($this->attributes['legacy_new_values'])) {
                $raw = $this->attributes['legacy_new_values'];
                $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                return is_array($decoded) ? $decoded : null;
            }

            return null;
        });
    }
}
