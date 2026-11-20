<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\InteractsWithAuditActivity;
use Database\Factories\AuditTrailFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

#[Table(name: 'activity_log')]
#[Hidden(['properties', 'attribute_changes'])]
final class Activity extends SpatieActivity implements ActivityContract
{
    /** @use HasFactory<AuditTrailFactory> */
    use HasFactory;

    use InteractsWithAuditActivity;

    public const ?string UPDATED_AT = null;
}
