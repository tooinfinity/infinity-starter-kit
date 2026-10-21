<?php

declare(strict_types=1);

namespace App\Actions\AuditTrails;

use App\Models\AuditTrail;
use Illuminate\Support\Facades\Date;

final readonly class DeleteAuditTrails
{
    public function handle(int $days): void
    {
        $cutoff = Date::now()->subDays($days);

        AuditTrail::query()->where('created_at', '<', $cutoff)->delete();
    }
}
