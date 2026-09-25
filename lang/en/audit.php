<?php

declare(strict_types=1);

return [
    'title' => 'Audit Trails',
    'description' => 'Track and monitor administrative and security events.',

    'events' => [
        'user_created' => 'User Created',
        'user_updated' => 'User Updated',
        'user_activated' => 'User Activated',
        'user_deactivated' => 'User Deactivated',
        'user_deleted' => 'User Deleted',
        'user_password_changed' => 'Password Changed',
        'settings_updated' => 'Settings Updated',
    ],

    'filters' => [
        'all_events' => 'All Events',
        'search_placeholder' => 'Search by IP, URL, or user...',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'reset' => 'Reset Filters',
    ],

    'table' => [
        'event' => 'Event',
        'user' => 'User',
        'auditable' => 'Target',
        'ip_address' => 'IP Address',
        'date' => 'Date',
        'actions' => 'Actions',
        'system' => 'System',
        'view_details' => 'View Details',
        'empty' => 'No audit trails found.',
    ],

    'detail' => [
        'title' => 'Audit Trail Details',
        'description' => 'Detailed information about this recorded event.',
        'event' => 'Event',
        'actor' => 'Actor',
        'target' => 'Target',
        'ip_address' => 'IP Address',
        'user_agent' => 'User Agent',
        'url' => 'Request URL',
        'date' => 'Timestamp',
        'tags' => 'Tags',
        'changes' => 'Changes',
        'field' => 'Field',
        'old_value' => 'Old Value',
        'new_value' => 'New Value',
        'no_changes' => 'No changes recorded for this event.',
        'close' => 'Close',
    ],
];
