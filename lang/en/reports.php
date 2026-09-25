<?php

declare(strict_types=1);

return [
    'title' => 'Reports',
    'description' => 'View system and administrative reporting and analytics.',

    'categories' => [
        'users' => 'Users',
        'security' => 'Security & Audit',
    ],

    'types' => [
        'user_activity' => [
            'title' => 'User Activity & Growth',
            'description' => 'Analyze user registrations, active statuses, and account trends.',
        ],
        'audit_activity' => [
            'title' => 'Audit Trail Activity',
            'description' => 'Monitor administrative actions, security events, and audit volume.',
        ],
    ],

    'metrics' => [
        'total_users' => 'Total Users',
        'total_users_description' => 'All registered accounts',
        'active_users' => 'Active Users',
        'active_users_description' => 'Enabled user accounts',
        'inactive_users' => 'Inactive Users',
        'inactive_users_description' => 'Deactivated user accounts',
        'new_users_in_period' => 'New Registrations',
        'new_users_in_period_description' => 'Created within selected date range',
        'total_audit_events' => 'Total Audit Events',
        'total_audit_events_description' => 'Recorded in selected period',
        'unique_actors' => 'Active Actors',
        'unique_actors_description' => 'Unique users triggering events',
        'top_event' => 'Top Event',
        'top_resource' => 'Top Resource',
    ],

    'common' => [
        'none' => 'None',
        'occurrences' => 'events',
        'no_events_recorded' => 'No events recorded',
        'no_resources_recorded' => 'No resources recorded',
        'active' => 'Active',
        'inactive' => 'Inactive',
        'system' => 'System',
        'export_csv' => 'Export CSV',
        'reset' => 'Reset Filters',
        'filter' => 'Filter',
        'search_placeholder' => 'Search...',
        'date_from' => 'Date From',
        'date_to' => 'Date To',
        'status' => 'Status',
        'all' => 'All',
        'all_events' => 'All Events',
        'view_report' => 'View Report',
        'presets' => [
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'last_90_days' => 'Last 90 Days',
            'last_365_days' => 'Last 365 Days',
            'custom' => 'Custom Range',
        ],
        'empty_title' => 'No data found',
        'empty_description' => 'No records match the selected date range and filter criteria.',
        'chart_view' => 'Visual Trend',
        'table_view' => 'Data Table',
        'distribution' => 'Event Distribution',
    ],

    'export' => [
        'columns' => [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'status' => 'Status',
            'roles' => 'Roles',
            'event' => 'Event',
            'actor_name' => 'Actor Name',
            'actor_email' => 'Actor Email',
            'target_type' => 'Resource Type',
            'target_id' => 'Resource ID',
            'ip_address' => 'IP Address',
            'created_at' => 'Timestamp',
        ],
    ],
];
