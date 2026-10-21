<?php

declare(strict_types=1);

namespace App\Actions\AuditTrails;

use App\Models\AuditTrail;
use Illuminate\Support\Facades\Date;

final readonly class DeleteAuditTrails
{
    public function handle(int $days, int $chunkSize = 1000): void
    {
        $cutoff = Date::now()->subDays($days);

        do {
            $deleted = AuditTrail::query()
                ->where('created_at', '<', $cutoff)
                ->limit($chunkSize)
                ->delete();
        } while ($deleted > 0);
    }
}
