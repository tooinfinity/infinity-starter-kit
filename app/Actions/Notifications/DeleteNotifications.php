<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class DeleteNotifications
{
    public function handle(int $retentionDays = 30, ?int $unreadRetentionDays = null, int $chunkSize = 1000): void
    {
        $batchSize = max(1, $chunkSize);

        if ($retentionDays > 0) {
            $readCutoff = Date::now()->subDays($retentionDays);

            do {
                $deleted = DB::table('notifications')
                    ->whereNotNull('read_at')
                    ->where('read_at', '<', $readCutoff)
                    ->limit($batchSize)
                    ->delete();
            } while ($deleted > 0);
        }

        if ($unreadRetentionDays !== null && $unreadRetentionDays > 0) {
            $unreadCutoff = Date::now()->subDays($unreadRetentionDays);

            do {
                $deleted = DB::table('notifications')
                    ->whereNull('read_at')
                    ->where('created_at', '<', $unreadCutoff)
                    ->limit($batchSize)
                    ->delete();
            } while ($deleted > 0);
        }
    }
}
