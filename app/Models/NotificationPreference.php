<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use Carbon\CarbonInterface;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* @chisel-notifications */

/**
 * @property-read string $id
 * @property-read string $user_id
 * @property-read NotificationType $notification_type
 * @property-read bool $database_enabled
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable([
    'user_id',
    'notification_type',
    'database_enabled',
])]
final class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'notification_type' => NotificationType::class,
            'database_enabled' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

/* @end-chisel-notifications */
