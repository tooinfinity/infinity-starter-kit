<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * @property string $id
 * @property string|null $log_name
 * @property string|null $description
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $causer_type
 * @property string|null $causer_id
 * @property AuditEvent|string|null $event
 * @property Collection<string, mixed>|null $attribute_changes
 * @property Collection<string, mixed>|null $properties
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Model|null $causer
 * @property-read Model|null $subject
 * @property-read string|null $user_id
 * @property-read string|null $auditable_type
 * @property-read string|null $auditable_id
 * @property-read string|null $actor_name
 * @property-read string|null $actor_email
 * @property-read string|null $url
 * @property-read string|null $ip_address
 * @property-read string|null $user_agent
 * @property-read array<string, mixed>|null $old_values
 * @property-read array<string, mixed>|null $new_values
 * @property-read list<string>|null $tags
 * @property-read User|null $user
 * @property-read Model|null $auditable
 */
trait InteractsWithAuditActivity
{
    use HasUuids;

    public static function bootInteractsWithAuditActivity(): void
    {
        self::updating(function (): never {
            throw new RuntimeException('Audit trail records are immutable and cannot be updated.');
        });

        self::deleting(function (): never {
            throw new RuntimeException('Audit trail records cannot be individually deleted. Use the retention prune command instead.');
        });

        self::creating(function (Model $model): void {
            if (empty($model->getAttribute('description'))) {
                $event = $model->getAttribute('event');
                $description = $event instanceof AuditEvent
                    ? $event->label()
                    : (is_string($event) && $event !== '' ? $event : 'default');

                $model->setAttribute('description', $description);
            }

            if (empty($model->getAttribute('log_name'))) {
                $model->setAttribute('log_name', 'default');
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return Builder<Model>
     */
    public function newEloquentBuilder($query): Builder
    {
        return new class($query) extends Builder
        {
            /**
             * @param  Closure|string|array<int|string, mixed>|Expression  $column
             * @param  mixed  $operator
             * @param  mixed  $value
             * @param  string  $boolean
             * @return $this
             */
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                $translateColumn = static fn (mixed $col): mixed => match ($col) {
                    'user_id' => 'causer_id',
                    'auditable_id' => 'subject_id',
                    'auditable_type' => 'subject_type',
                    'actor_email' => 'properties->actor->email',
                    'actor_name' => 'properties->actor->name',
                    'ip_address' => 'properties->request->ip_address',
                    'user_agent' => 'properties->request->user_agent',
                    'url' => 'properties->request->url',
                    default => $col,
                };

                if (is_string($column)) {
                    $column = $translateColumn($column);
                } elseif (is_array($column)) {
                    $translated = [];
                    foreach ($column as $key => $val) {
                        if (is_array($val) && isset($val[0])) {
                            $val[0] = $translateColumn($val[0]);
                            $translated[$key] = $val;
                        } else {
                            $newKey = is_string($key) ? $translateColumn($key) : $key;
                            $translated[$newKey] = $val;
                        }
                    }

                    $column = $translated;
                }

                if (func_num_args() === 1) {
                    return parent::where($column);
                }

                if (func_num_args() === 2) {
                    return parent::where($column, $operator);
                }

                if (func_num_args() === 3) {
                    return parent::where($column, $operator, $value);
                }

                return parent::where($column, $operator, $value, $boolean);
            }
        };
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function user(): MorphTo
    {
        return $this->morphTo('user', 'causer_type', 'causer_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo('auditable', 'subject_type', 'subject_id');
    }

    public function setProperty(string $key, mixed $value): static
    {
        /** @var array<string, mixed> $properties */
        $properties = $this->properties?->toArray() ?? [];

        Arr::set($properties, $key, $value);
        $this->attributes['properties'] = json_encode($properties);
        $this->properties = collect($properties);

        return $this;
    }

    public function setAttributeChange(string $key, mixed $value): static
    {
        /** @var array<string, mixed> $changes */
        $changes = $this->attribute_changes?->toArray() ?? [];

        Arr::set($changes, $key, $value);
        $this->attributes['attribute_changes'] = json_encode($changes);
        $this->attribute_changes = collect($changes);

        return $this;
    }

    protected function getUserIdAttribute(): ?string
    {
        return $this->causer_id !== null ? (string) $this->causer_id : null;
    }

    protected function setUserIdAttribute(?string $value): void
    {
        $this->attributes['causer_id'] = $value;
        if ($value !== null && ! isset($this->attributes['causer_type'])) {
            $this->attributes['causer_type'] = User::class;
        }
    }

    protected function getAuditableIdAttribute(): ?string
    {
        return $this->subject_id !== null ? (string) $this->subject_id : null;
    }

    protected function setAuditableIdAttribute(?string $value): void
    {
        $this->attributes['subject_id'] = $value;
    }

    protected function getAuditableTypeAttribute(): ?string
    {
        return $this->subject_type;
    }

    protected function setAuditableTypeAttribute(?string $value): void
    {
        $this->attributes['subject_type'] = $value;
    }

    protected function getActorNameAttribute(): ?string
    {
        $name = $this->getProperty('actor.name');

        return is_string($name) ? $name : null;
    }

    protected function setActorNameAttribute(?string $value): void
    {
        $this->setProperty('actor.name', $value);
    }

    protected function getActorEmailAttribute(): ?string
    {
        $email = $this->getProperty('actor.email');

        return is_string($email) ? $email : null;
    }

    protected function setActorEmailAttribute(?string $value): void
    {
        $this->setProperty('actor.email', $value);
    }

    protected function getUrlAttribute(): ?string
    {
        $url = $this->getProperty('request.url');

        return is_string($url) ? $url : null;
    }

    protected function setUrlAttribute(?string $value): void
    {
        $this->setProperty('request.url', $value);
    }

    protected function getIpAddressAttribute(): ?string
    {
        $ip = $this->getProperty('request.ip_address');

        return is_string($ip) ? $ip : null;
    }

    protected function setIpAddressAttribute(?string $value): void
    {
        $this->setProperty('request.ip_address', $value);
    }

    protected function getUserAgentAttribute(): ?string
    {
        $userAgent = $this->getProperty('request.user_agent');

        return is_string($userAgent) ? $userAgent : null;
    }

    protected function setUserAgentAttribute(?string $value): void
    {
        $this->setProperty('request.user_agent', $value);
    }

    /**
     * @return array<int, string>
     */
    protected function getTagsAttribute(): array
    {
        $tags = $this->getProperty('tags', []);

        return is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [];
    }

    /**
     * @param  array<int, string>|null  $value
     */
    protected function setTagsAttribute(?array $value): void
    {
        $this->setProperty('tags', $value ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOldValuesAttribute(): ?array
    {
        $old = $this->getProperty('old') ?? $this->attribute_changes?->get('old');

        if (! is_array($old)) {
            return null;
        }

        $result = [];
        foreach ($old as $key => $val) {
            $result[(string) $key] = $val;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function setOldValuesAttribute(?array $value): void
    {
        $this->setProperty('old', $value);
        $this->setAttributeChange('old', $value);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getNewValuesAttribute(): ?array
    {
        $new = $this->getProperty('attributes') ?? $this->attribute_changes?->get('attributes');

        if (! is_array($new)) {
            return null;
        }

        $result = [];
        foreach ($new as $key => $val) {
            $result[(string) $key] = $val;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function setNewValuesAttribute(?array $value): void
    {
        $this->setProperty('attributes', $value);
        $this->setAttributeChange('attributes', $value);
    }

    protected function getEventAttribute(mixed $value): AuditEvent|string|null
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return AuditEvent::tryFrom($value) ?? $value;
    }

    protected function setEventAttribute(mixed $value): void
    {
        $this->attributes['event'] = $value instanceof AuditEvent ? $value->value : $value;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'causer_id' => 'string',
            'subject_id' => 'string',
            'attribute_changes' => 'collection',
            'properties' => 'collection',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
