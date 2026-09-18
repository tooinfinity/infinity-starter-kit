<?php

declare(strict_types=1);

return [
    'title' => 'Notifications',
    'empty' => 'No notifications yet',
    'all_marked_as_read' => 'All notifications marked as read.',
    'deleted' => 'Notification deleted.',
    'preferences_updated' => 'Notification preferences updated successfully.',

    'types' => [
        'security' => 'Security',
        'user_management' => 'User Management',
        'system' => 'System',
    ],

    'password_changed' => [
        'title' => 'Password Changed',
        'body' => 'Your password was recently changed. If you did not make this change, please contact support immediately.',
    ],

    'user_activated' => [
        'title' => 'Account Activated',
        'body' => 'Your account has been activated by an administrator. You now have full access to the system.',
    ],

    'user_deactivated' => [
        'title' => 'Account Deactivated',
        'body' => 'Your account has been deactivated by an administrator. Please contact support if you believe this is an error.',
    ],
];
