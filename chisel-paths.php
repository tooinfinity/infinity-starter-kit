<?php

declare(strict_types=1);

/**
 * Framework-specific paths and definitions for Infinity Starter Kit (React + Inertia + TypeScript).
 */
return [
    'auth' => [
        'login' => 'resources/js/pages/session/create.tsx',
        'welcome' => 'resources/js/pages/welcome.tsx',
        'register' => 'resources/js/pages/user/create.tsx',
        'user_dir' => 'resources/js/pages/user',
        'verify_email' => 'resources/js/pages/user-email-verification-notification/create.tsx',
        'verify_email_dir' => 'resources/js/pages/user-email-verification-notification',
        'two_factor_show' => 'resources/js/pages/user-two-factor-authentication/show.tsx',
        'two_factor_challenge' => 'resources/js/pages/user-two-factor-authentication-challenge/show.tsx',
        'two_factor_dirs' => [
            'resources/js/pages/user-two-factor-authentication',
            'resources/js/pages/user-two-factor-authentication-challenge',
        ],
        'two_factor_files' => [
            'resources/js/components/two-factor-setup-modal.tsx',
            'resources/js/components/two-factor-recovery-codes.tsx',
            'resources/js/hooks/use-two-factor-auth.ts',
        ],
        'settings_layout' => 'resources/js/layouts/settings/layout.tsx',
        'profile' => 'resources/js/pages/user-profile/edit.tsx',
        'auth_types' => 'resources/js/types/auth.ts',
    ],

    'authorization' => [
        'files' => [
            'resources/js/components/can.tsx',
            'resources/js/hooks/use-authorization.ts',
        ],
        'composer_package' => 'spatie/laravel-permission',
        'empty_dirs' => [
            'tests/Feature/Authorization',
        ],
    ],

    'settings' => [
        'pages' => [
            'resources/js/pages/settings/application/edit.tsx',
        ],
        'types' => 'resources/js/types/settings.ts',
        'empty_dirs' => [
            'resources/js/pages/settings/application',
            'tests/Feature/Settings',
        ],
    ],

    'user_management' => [
        'components' => [
            'resources/js/components/users/user-status-badge.tsx',
        ],
        'pages' => [
            'resources/js/pages/users/index.tsx',
            'resources/js/pages/users/create.tsx',
            'resources/js/pages/users/edit.tsx',
        ],
        'types' => 'resources/js/types/users.ts',
        'empty_dirs' => [
            'app/Actions/Users',
            'app/Data/Users',
            'app/Http/Controllers/Users',
            'app/Http/Requests/Users',
            'app/Queries/Users',
            'resources/js/components/users',
            'resources/js/pages/users',
            'tests/Feature/Users',
        ],
    ],

    'localization' => [
        'components' => [
            'resources/js/components/language-selector.tsx',
        ],
        'types' => 'resources/js/types/localization.ts',
        'composer_package' => 'erag/laravel-lang-sync-inertia',
        'frontend_package' => '@erag/lang-sync-inertia',
        'empty_dirs' => [
            'tests/Feature/Localization',
            'lang/fr',
            'lang/ar',
        ],
    ],

    'notifications' => [
        'components' => [
            'resources/js/components/notifications/notification-bell.tsx',
            'resources/js/components/notifications/notification-dropdown.tsx',
            'resources/js/components/notifications/notification-empty-state.tsx',
            'resources/js/components/notifications/notification-item.tsx',
        ],
        'pages' => [
            'resources/js/pages/notifications/index.tsx',
            'resources/js/pages/settings/notifications/edit.tsx',
        ],
        'types' => 'resources/js/types/notifications.ts',
        'empty_dirs' => [
            'app/Actions/Notifications',
            'app/Http/Requests/Notifications',
            'app/Notifications',
            'resources/js/components/notifications',
            'resources/js/pages/notifications',
            'resources/js/pages/settings/notifications',
            'tests/Feature/Notifications',
            'tests/Unit/Actions/Notifications',
            'tests/Unit/Notifications',
        ],
    ],

    'audit_trails' => [
        'components' => [
            'resources/js/components/audit-trails/audit-trail-detail.tsx',
        ],
        'pages' => [
            'resources/js/pages/audit-trails/index.tsx',
        ],
        'types' => 'resources/js/types/audit-trails.ts',
        'empty_dirs' => [
            'app/Actions/AuditTrails',
            'app/Http/Controllers/AuditTrails',
            'app/Http/Requests/AuditTrails',
            'app/Queries/AuditTrails',
            'resources/js/components/audit-trails',
            'resources/js/pages/audit-trails',
            'tests/Feature/AuditTrails',
        ],
    ],

    'reporting' => [
        'components' => [
            'resources/js/components/reports/report-summary-cards.tsx',
            'resources/js/components/reports/report-chart.tsx',
            'resources/js/components/reports/report-date-range-filter.tsx',
        ],
        'pages' => [
            'resources/js/pages/reports/index.tsx',
            'resources/js/pages/reports/users.tsx',
            'resources/js/pages/reports/audit.tsx',
        ],
        'types' => 'resources/js/types/reports.ts',
        'empty_dirs' => [
            'app/Data/Reporting',
            'app/Queries/Reporting',
            'app/Http/Requests/Reporting',
            'app/Http/Controllers/Reporting',
            'resources/js/components/reports',
            'resources/js/pages/reports',
            'tests/Unit/Reporting',
            'tests/Feature/Reporting',
        ],
    ],
];
