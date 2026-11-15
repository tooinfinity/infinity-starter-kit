<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notifications\DeleteNotifications;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:prune {--days= : The number of days of read notifications to retain} {--unread-days= : The number of days of unread notifications to retain} {--chunk=1000 : The number of records to delete per chunk}')]
#[Description('Prune read and unread notifications older than their retention periods')]
final class PruneNotificationsCommand extends Command
{
    public function handle(DeleteNotifications $action): int
    {
        $daysOption = $this->option('days');
        $unreadDaysOption = $this->option('unread-days');
        $chunkOption = $this->option('chunk');

        if ($daysOption !== null && filter_var($daysOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $this->components->error('The --days option must be an integer greater than or equal to 0.');

            return self::FAILURE;
        }

        if ($unreadDaysOption !== null && filter_var($unreadDaysOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $this->components->error('The --unread-days option must be an integer greater than or equal to 0.');

            return self::FAILURE;
        }

        if ($chunkOption !== null && filter_var($chunkOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->components->error('The --chunk option must be an integer greater than 0.');

            return self::FAILURE;
        }

        $retentionDays = config()->integer('notifications.retention_days', 30);
        $readDays = $daysOption !== null ? (int) $daysOption : $retentionDays;

        $configuredUnread = config('notifications.unread_retention_days');
        $unreadDays = $unreadDaysOption !== null
            ? (int) $unreadDaysOption
            : (is_numeric($configuredUnread) ? (int) $configuredUnread : null);

        $chunkSize = $chunkOption !== null ? (int) $chunkOption : 1000;

        if ($readDays <= 0 && ($unreadDays === null || $unreadDays <= 0)) {
            $this->components->warn('Notification pruning is disabled (retention days is 0 or less).');

            return self::SUCCESS;
        }

        $this->components->info('Pruning notifications...');

        $action->handle($readDays, $unreadDays, $chunkSize);

        $this->components->info('Notifications pruned successfully.');

        return self::SUCCESS;
    }
}
