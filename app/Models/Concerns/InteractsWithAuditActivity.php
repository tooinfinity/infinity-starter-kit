<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
             * @param  mixed  $column
             * @param  mixed  $operator
             * @param  mixed  $value
             * @param  string  $boolean
             * @return $this
             */
            public function where($column, $operator = null, $value = null, $boolean = 'and')
            {
                if ($column === 'user_id') {
                    $column = 'causer_id';
                } elseif ($column === 'auditable_id') {
                    $column = 'subject_id';
                } elseif ($column === 'auditable_type') {
                    $column = 'subject_type';
                } elseif ($column === 'actor_email') {
                    $column = 'properties->actor->email';
                } elseif ($column === 'actor_name') {
                    $column = 'properties->actor->name';
                } elseif ($column === 'ip_address') {
                    $column = 'properties->request->ip_address';
                } elseif ($column === 'user_agent') {
                    $column = 'properties->request->user_agent';
                } elseif ($column === 'url') {
                    $column = 'properties->request->url';
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
        return $this->morphTo('causer', 'causer_type', 'causer_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo('subject', 'subject_type', 'subject_id');
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

    protected function getAuditableTypeAttribute(): ?string
    {
        return $this->subject_type;
    }

    protected function setAuditableTypeAttribute(?string $value): void
    {
        $this->attributes['subject_type'] = $value;
    }

    protected function getAuditableIdAttribute(): ?string
    {
        return $this->subject_id !== null ? (string) $this->subject_id : null;
    }

    protected function setAuditableIdAttribute(?string $value): void
    {
        $this->attributes['subject_id'] = $value;
    }

    protected function getActorNameAttribute(): ?string
    {
        /** @var mixed $actor */
        $actor = $this->getProperty('actor');

        if (! is_array($actor)) {
            return null;
        }

        $name = $actor['name'] ?? null;

        return is_string($name) ? $name : null;
    }

    protected function setActorNameAttribute(?string $value): void
    {
        $props = $this->getPropertiesArray();
        $actor = isset($props['actor']) && is_array($props['actor']) ? $props['actor'] : [];
        $actor['name'] = $value;
        $props['actor'] = $actor;
        $this->setPropertiesArray($props);
    }

    protected function getActorEmailAttribute(): ?string
    {
        /** @var mixed $actor */
        $actor = $this->getProperty('actor');

        if (! is_array($actor)) {
            return null;
        }

        $email = $actor['email'] ?? null;

        return is_string($email) ? $email : null;
    }

    protected function setActorEmailAttribute(?string $value): void
    {
        $props = $this->getPropertiesArray();
        $actor = isset($props['actor']) && is_array($props['actor']) ? $props['actor'] : [];
        $actor['email'] = $value;
        $props['actor'] = $actor;
        $this->setPropertiesArray($props);
    }

    protected function getUrlAttribute(): ?string
    {
        /** @var mixed $req */
        $req = $this->getProperty('request');

        if (! is_array($req)) {
            return null;
        }

        $url = $req['url'] ?? null;

        return is_string($url) ? $url : null;
    }

    protected function setUrlAttribute(?string $value): void
    {
        $props = $this->getPropertiesArray();
        $request = isset($props['request']) && is_array($props['request']) ? $props['request'] : [];
        $request['url'] = $value;
        $props['request'] = $request;
        $this->setPropertiesArray($props);
    }

    protected function getIpAddressAttribute(): ?string
    {
        /** @var mixed $req */
        $req = $this->getProperty('request');

        if (! is_array($req)) {
            return null;
        }

        $ip = $req['ip_address'] ?? null;

        return is_string($ip) ? $ip : null;
    }

    protected function setIpAddressAttribute(?string $value): void
    {
        $props = $this->getPropertiesArray();
        $request = isset($props['request']) && is_array($props['request']) ? $props['request'] : [];
        $request['ip_address'] = $value;
        $props['request'] = $request;
        $this->setPropertiesArray($props);
    }

    protected function getUserAgentAttribute(): ?string
    {
        /** @var mixed $req */
        $req = $this->getProperty('request');

        if (! is_array($req)) {
            return null;
        }

        $agent = $req['user_agent'] ?? null;

        return is_string($agent) ? $agent : null;
    }

    protected function setUserAgentAttribute(?string $value): void
    {
        $props = $this->getPropertiesArray();
        $request = isset($props['request']) && is_array($props['request']) ? $props['request'] : [];
        $request['user_agent'] = $value;
        $props['request'] = $request;
        $this->setPropertiesArray($props);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOldValuesAttribute(): ?array
    {
        $old = $this->getProperty('old');

        if (! is_array($old)) {
            return null;
        }

        /** @var array<string, mixed> $old */
        return $old;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function setOldValuesAttribute(?array $value): void
    {
        $props = $this->getPropertiesArray();
        if ($value === null) {
            unset($props['old']);
        } else {
            $props['old'] = $value;
        }

        $this->setPropertiesArray($props);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getNewValuesAttribute(): ?array
    {
        $new = $this->getProperty('attributes') ?? $this->getProperty('new');

        if (! is_array($new)) {
            return null;
        }

        /** @var array<string, mixed> $new */
        return $new;
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    protected function setNewValuesAttribute(?array $value): void
    {
        $props = $this->getPropertiesArray();
        if ($value === null) {
            unset($props['attributes']);
        } else {
            $props['attributes'] = $value;
        }

        $this->setPropertiesArray($props);
    }

    /**
     * @return list<string>|null
     */
    protected function getTagsAttribute(): ?array
    {
        $tags = $this->getProperty('tags');

        return is_array($tags) ? array_values(array_filter($tags, is_string(...))) : null;
    }

    /**
     * @param  list<string>|null  $value
     */
    protected function setTagsAttribute(?array $value): void
    {
        $props = $this->getPropertiesArray();
        if ($value === null) {
            unset($props['tags']);
        } else {
            $props['tags'] = $value;
        }

        $this->setPropertiesArray($props);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'event' => AuditEvent::class,
            'causer_id' => 'string',
            'subject_id' => 'string',
            'attribute_changes' => 'collection',
            'properties' => 'collection',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPropertiesArray(): array
    {
        $properties = $this->attributes['properties'] ?? null;
        if (is_string($properties)) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($properties, true) ?? [];

            return $decoded;
        }

        if ($properties instanceof Collection) {
            /** @var array<string, mixed> $array */
            $array = $properties->toArray();

            return $array;
        }

        if (is_array($properties)) {
            /** @var array<string, mixed> $properties */
            return $properties;
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function setPropertiesArray(array $props): void
    {
        $this->attributes['properties'] = json_encode($props, JSON_THROW_ON_ERROR);
    }
}
