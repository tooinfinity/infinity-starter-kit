<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use Carbon\CarbonInterface;
use Database\Factories\AuditTrailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read string $id
 * @property-read string|null $user_id
 * @property-read AuditEvent $event
 * @property-read string|null $auditable_type
 * @property-read string|null $auditable_id
 * @property-read array<string, mixed>|null $old_values
 * @property-read array<string, mixed>|null $new_values
 * @property-read string|null $url
 * @property-read string|null $ip_address
 * @property-read string|null $user_agent
 * @property-read array<int, string>|null $tags
 * @property-read CarbonInterface|null $created_at
 * @property-read User|null $user
 * @property-read Model|null $auditable
 */
#[Fillable([
    'user_id',
    'event',
    'auditable_type',
    'auditable_id',
    'old_values',
    'new_values',
    'url',
    'ip_address',
    'user_agent',
    'tags',
    'created_at',
])]
final class AuditTrail extends Model
{
    /** @use HasFactory<AuditTrailFactory> */
    use HasFactory;

    use HasUuids;

    public const ?string UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'event' => AuditEvent::class,
            'auditable_type' => 'string',
            'auditable_id' => 'string',
            'old_values' => 'array',
            'new_values' => 'array',
            'url' => 'string',
            'ip_address' => 'string',
            'user_agent' => 'string',
            'tags' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
