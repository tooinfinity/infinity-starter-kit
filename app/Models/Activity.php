<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use RuntimeException;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

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
}
