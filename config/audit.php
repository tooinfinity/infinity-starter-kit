<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Trail Retention Days
    |--------------------------------------------------------------------------
    |
    | The number of days audit trail records should be retained before
    | being pruned. A value of 0 or null disables automatic pruning.
    |
    */
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
];
