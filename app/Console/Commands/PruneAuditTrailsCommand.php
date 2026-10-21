<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\AuditTrails\DeleteAuditTrails;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('audit:prune {--days= : The number of days of audit logs to retain}')]
#[Description('Prune audit trail records older than the retention period')]
final class PruneAuditTrailsCommand extends Command
{
    public function handle(DeleteAuditTrails $action): int
    {
        $daysOption = $this->option('days');
        $retentionDays = config()->integer('audit.retention_days', 365);

        $days = is_numeric($daysOption)
            ? (int) $daysOption
            : $retentionDays;

        if ($days <= 0) {
            $this->components->warn('Audit trail pruning is disabled (retention days is 0 or less).');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Pruning audit trail records older than %d days...', $days));

        $action->handle($days);

        $this->components->info('Audit trail records pruned successfully.');

        return self::SUCCESS;
    }
}
