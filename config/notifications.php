<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Retention Days
    |--------------------------------------------------------------------------
    |
    | The number of days read notifications should be retained before
    | being pruned by the scheduler. Setting this value to 0 or a
    | negative integer disables pruning of read notifications.
    |
    */

    'retention_days' => (int) env('NOTIFICATION_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Unread Notification Retention Days
    |--------------------------------------------------------------------------
    |
    | The number of days unread notifications should be retained before
    | being pruned. By default, unread notifications never expire (null).
    | Setting this to an integer greater than 0 will prune unread notifications
    | older than that many days.
    |
    */

    'unread_retention_days' => env('NOTIFICATION_UNREAD_RETENTION_DAYS') !== null
        ? (int) env('NOTIFICATION_UNREAD_RETENTION_DAYS')
        : null,

];
