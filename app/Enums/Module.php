<?php

declare(strict_types=1);

namespace App\Enums;

enum Module: string
{
    case Authorization = 'authorization';
    case Settings = 'settings';
    case UserManagement = 'user-management';
    case Localization = 'localization';
    case Notifications = 'notifications';
    case AuditTrails = 'audit-trails';
    case Reporting = 'reporting';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function defaultValues(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Authorization => 'Authorization',
            self::Settings => 'Settings',
            self::UserManagement => 'User Management',
            self::Localization => 'Localization',
            self::Notifications => 'Notifications',
            self::AuditTrails => 'Audit Trails',
            self::Reporting => 'Reporting',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Authorization => 'Role-based access control powered by Spatie Laravel Permission',
            self::Settings => 'Application configuration and key-value settings management',
            self::UserManagement => 'User directory, profile administration, and role management',
            self::Localization => 'Multi-language support (EN, FR, AR), RTL handling, and locale selector',
            self::Notifications => 'Database and email notification center with preference management',
            self::AuditTrails => 'Searchable activity log tracking actions, IPs, and timestamps',
            self::Reporting => 'Read-only analytics, interactive SVG charts, and streaming CSV exports',
        };
    }

    /**
     * @return list<self>
     */
    public function dependencies(): array
    {
        return match ($this) {
            self::Authorization => [],
            self::Settings => [],
            self::UserManagement => [self::Authorization],
            self::Localization => [],
            self::Notifications => [self::Localization],
            self::AuditTrails => [],
            self::Reporting => [self::Authorization, self::AuditTrails, self::UserManagement],
        };
    }

    public function chiselTag(): string
    {
        return match ($this) {
            self::Authorization => 'roles-permissions',
            self::Settings => 'settings',
            self::UserManagement => 'user-management',
            self::Localization => 'localization',
            self::Notifications => 'notifications',
            self::AuditTrails => 'audit-trails',
            self::Reporting => 'reporting',
        };
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Authorization => [
                'authorization.manage',
            ],
            self::Settings => [
                'settings.manage',
            ],
            self::UserManagement => [
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.manage-roles',
                'users.manage-password',
            ],
            self::Localization => [],
            self::Notifications => [],
            self::AuditTrails => [
                'audit.view',
            ],
            self::Reporting => [
                'reports.view',
                'reports.export',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function composerPackages(): array
    {
        return match ($this) {
            self::Authorization => ['spatie/laravel-permission'],
            self::Settings => [],
            self::UserManagement => ['spatie/laravel-data'],
            self::Localization => ['erag/laravel-lang-sync-inertia'],
            self::Notifications => [],
            self::AuditTrails => [],
            self::Reporting => ['spatie/laravel-data'],
        };
    }

    /**
     * @return list<string>
     */
    public function npmPackages(): array
    {
        return match ($this) {
            self::Localization => ['@erag/lang-sync-inertia'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public function routes(): array
    {
        return match ($this) {
            self::Authorization => [],
            self::Settings => [
                'settings.edit',
                'settings.update',
            ],
            self::UserManagement => [
                'users.index',
                'users.create',
                'users.store',
                'users.edit',
                'users.update',
                'users.activate',
                'users.deactivate',
                'users.password.update',
                'users.destroy',
            ],
            self::Localization => [
                'locale.update',
            ],
            self::Notifications => [
                'notifications.index',
                'notifications.mark-read',
                'notifications.mark-all-read',
                'notifications.destroy',
                'notification-preferences.edit',
                'notification-preferences.update',
            ],
            self::AuditTrails => [
                'audit-trails.index',
            ],
            self::Reporting => [
                'reports.index',
                'reports.users',
                'reports.users.export',
                'reports.audit',
                'reports.audit.export',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function ownedFiles(): array
    {
        return match ($this) {
            self::Authorization => [
                'config/permission.php',
                'database/migrations/2026_01_01_000002_create_permission_tables.php',
                'app/Enums/Permission.php',
                'app/Enums/Role.php',
                'app/Console/Commands/SetupAuthorizationCommand.php',
                'app/Console/Commands/SetupAdminUserCommand.php',
                'resources/js/hooks/use-authorization.ts',
                'resources/js/components/can.tsx',
                'tests/Feature/Authorization/SpatieRbacTest.php',
                'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
                'tests/Unit/Enums/PermissionTest.php',
                'tests/Unit/Enums/RoleTest.php',
            ],
            self::Settings => [
                'app/Enums/SettingKey.php',
                'app/Enums/SettingGroup.php',
                'app/Models/Setting.php',
                'app/Actions/GetSetting.php',
                'app/Actions/UpdateSettings.php',
                'app/Http/Controllers/SettingController.php',
                'app/Http/Requests/UpdateSettingsRequest.php',
                'database/factories/SettingFactory.php',
                'database/migrations/2026_01_01_000003_create_settings_table.php',
                'resources/js/pages/settings/application/edit.tsx',
                'resources/js/types/settings.ts',
                'tests/Unit/Models/SettingTest.php',
                'tests/Unit/Enums/SettingKeyTest.php',
                'tests/Unit/Enums/SettingGroupTest.php',
                'tests/Feature/Controllers/SettingControllerTest.php',
                'tests/Feature/Settings/SettingsActionTest.php',
            ],
            self::UserManagement => [
                'app/Actions/Users/CreateUserAction.php',
                'app/Actions/Users/UpdateUserAction.php',
                'app/Actions/Users/ActivateUserAction.php',
                'app/Actions/Users/DeactivateUserAction.php',
                'app/Actions/Users/ChangeUserPasswordAction.php',
                'app/Actions/Users/DeleteUserAction.php',
                'app/Data/Users/CreateUserData.php',
                'app/Data/Users/UpdateUserData.php',
                'app/Http/Controllers/Users/UserController.php',
                'app/Http/Controllers/Users/ActivateUserController.php',
                'app/Http/Controllers/Users/DeactivateUserController.php',
                'app/Http/Controllers/Users/UserPasswordController.php',
                'app/Http/Middleware/EnsureUserIsActive.php',
                'app/Http/Requests/Users/StoreUserRequest.php',
                'app/Http/Requests/Users/UpdateUserRequest.php',
                'app/Http/Requests/Users/UpdateUserRolesRequest.php',
                'app/Http/Requests/Users/UpdateUserPasswordRequest.php',
                'app/Http/Requests/Users/ActivateUserRequest.php',
                'app/Http/Requests/Users/DeactivateUserRequest.php',
                'app/Http/Requests/Users/DeleteUserRequest.php',
                'app/Queries/Users/UserListingQuery.php',
                'resources/js/components/users/user-status-badge.tsx',
                'resources/js/pages/users/index.tsx',
                'resources/js/pages/users/create.tsx',
                'resources/js/pages/users/edit.tsx',
                'resources/js/types/users.ts',
                'tests/Feature/Users/UserListingTest.php',
                'tests/Feature/Users/CreateUserTest.php',
                'tests/Feature/Users/UpdateUserTest.php',
                'tests/Feature/Users/ActivateDeactivateUserTest.php',
                'tests/Feature/Users/ChangePasswordTest.php',
                'tests/Feature/Users/DeleteUserTest.php',
                'tests/Feature/Users/InactiveUserAuthTest.php',
                'tests/Feature/Users/SuperAdminProtectionTest.php',
                'tests/Feature/Users/UpdateUserRolesRequestTest.php',
            ],
            self::Localization => [
                'app/Actions/ChangeLocale.php',
                'app/Actions/ResolveLocale.php',
                'app/Enums/Locale.php',
                'app/Http/Controllers/LocaleController.php',
                'app/Http/Middleware/HandleLocale.php',
                'app/Http/Requests/ChangeLocaleRequest.php',
                'lang/en/common.php',
                'lang/en/localization.php',
                'lang/fr/common.php',
                'lang/fr/localization.php',
                'lang/ar/common.php',
                'lang/ar/localization.php',
                'resources/js/components/language-selector.tsx',
                'resources/js/types/localization.ts',
                'tests/Unit/Enums/LocaleTest.php',
                'tests/Feature/Localization/ChangeLocaleTest.php',
                'tests/Feature/Localization/InertiaLocalePropsTest.php',
                'tests/Feature/Localization/LocaleMiddlewareTest.php',
                'tests/Feature/Localization/ResolveLocaleTest.php',
                'tests/Feature/Localization/TranslationFileTest.php',
            ],
            self::Notifications => [
                'app/Actions/Notifications/DeleteNotification.php',
                'app/Actions/Notifications/DeleteReadNotifications.php',
                'app/Actions/Notifications/MarkAllNotificationsAsRead.php',
                'app/Actions/Notifications/MarkNotificationAsRead.php',
                'app/Actions/Notifications/UpdateNotificationPreferences.php',
                'app/Enums/NotificationType.php',
                'app/Http/Controllers/MarkAllNotificationsAsReadController.php',
                'app/Http/Controllers/NotificationController.php',
                'app/Http/Controllers/NotificationPreferenceController.php',
                'app/Http/Requests/Notifications/DeleteNotificationRequest.php',
                'app/Http/Requests/Notifications/MarkNotificationAsReadRequest.php',
                'app/Http/Requests/Notifications/UpdateNotificationPreferencesRequest.php',
                'app/Models/NotificationPreference.php',
                'app/Notifications/PasswordChanged.php',
                'app/Notifications/UserActivated.php',
                'app/Notifications/UserDeactivated.php',
                'database/factories/NotificationPreferenceFactory.php',
                'database/migrations/2026_09_18_224415_create_notifications_table.php',
                'database/migrations/2026_09_18_224442_create_notification_preferences_table.php',
                'lang/en/notifications.php',
                'lang/fr/notifications.php',
                'lang/ar/notifications.php',
                'resources/js/components/notifications/notification-bell.tsx',
                'resources/js/components/notifications/notification-dropdown.tsx',
                'resources/js/components/notifications/notification-empty-state.tsx',
                'resources/js/components/notifications/notification-item.tsx',
                'resources/js/pages/notifications/index.tsx',
                'resources/js/pages/settings/notifications/edit.tsx',
                'resources/js/types/notifications.ts',
                'tests/Feature/Notifications/DeleteNotificationTest.php',
                'tests/Feature/Notifications/InertiaNotificationPropsTest.php',
                'tests/Feature/Notifications/MarkNotificationReadTest.php',
                'tests/Feature/Notifications/NotificationListingTest.php',
                'tests/Feature/Notifications/NotificationLocalizationTest.php',
                'tests/Feature/Notifications/NotificationPreferencesTest.php',
                'tests/Feature/Notifications/NotificationSecurityTest.php',
                'tests/Unit/Enums/NotificationTypeTest.php',
                'tests/Unit/Notifications/PasswordChangedTest.php',
                'tests/Unit/Actions/Notifications/DeleteReadNotificationsTest.php',
            ],
            self::AuditTrails => [
                'app/Actions/AuditTrails/RecordAuditTrail.php',
                'app/Enums/AuditEvent.php',
                'app/Http/Controllers/AuditTrails/AuditTrailController.php',
                'app/Http/Requests/AuditTrails/AuditTrailIndexRequest.php',
                'app/Models/AuditTrail.php',
                'app/Queries/AuditTrails/AuditTrailListingQuery.php',
                'database/factories/AuditTrailFactory.php',
                'database/migrations/2026_09_20_000000_create_audit_trails_table.php',
                'lang/en/audit.php',
                'lang/fr/audit.php',
                'lang/ar/audit.php',
                'resources/js/components/audit-trails/audit-trail-detail.tsx',
                'resources/js/pages/audit-trails/index.tsx',
                'resources/js/types/audit-trails.ts',
                'tests/Feature/AuditTrails/AuditTrailListingTest.php',
                'tests/Feature/AuditTrails/AuditTrailRemovalTest.php',
                'tests/Feature/AuditTrails/AuditTrailSecurityTest.php',
                'tests/Feature/AuditTrails/AuditTrailTransactionTest.php',
                'tests/Feature/AuditTrails/RecordAuditTrailTest.php',
                'tests/Feature/AuditTrails/SettingsAuditingTest.php',
                'tests/Feature/AuditTrails/UserAuditingTest.php',
                'tests/Unit/AuditEventEnumTest.php',
            ],
            self::Reporting => [
                'app/Enums/ReportCategory.php',
                'app/Enums/ReportType.php',
                'app/Data/Reporting/ReportSummaryCardData.php',
                'app/Data/Reporting/ReportTimeSeriesPointData.php',
                'app/Data/Reporting/ReportBreakdownItemData.php',
                'app/Data/Reporting/ReportMetadataData.php',
                'app/Queries/Reporting/UserReportQuery.php',
                'app/Queries/Reporting/AuditReportQuery.php',
                'app/Queries/Reporting/ExportUserReportStream.php',
                'app/Queries/Reporting/ExportAuditReportStream.php',
                'app/Http/Requests/Reporting/UserReportRequest.php',
                'app/Http/Requests/Reporting/AuditReportRequest.php',
                'app/Http/Requests/Reporting/ExportReportRequest.php',
                'app/Http/Controllers/Reporting/ReportIndexController.php',
                'app/Http/Controllers/Reporting/UserReportController.php',
                'app/Http/Controllers/Reporting/ExportUserReportController.php',
                'app/Http/Controllers/Reporting/AuditReportController.php',
                'app/Http/Controllers/Reporting/ExportAuditReportController.php',
                'lang/en/reports.php',
                'lang/fr/reports.php',
                'lang/ar/reports.php',
                'resources/js/components/reports/report-summary-cards.tsx',
                'resources/js/components/reports/report-chart.tsx',
                'resources/js/components/reports/report-date-range-filter.tsx',
                'resources/js/pages/reports/index.tsx',
                'resources/js/pages/reports/users.tsx',
                'resources/js/pages/reports/audit.tsx',
                'resources/js/types/reports.ts',
                'tests/Unit/Reporting/ReportTypeTest.php',
                'tests/Unit/Reporting/ReportCategoryTest.php',
                'tests/Feature/Reporting/UserReportQueryTest.php',
                'tests/Feature/Reporting/AuditReportQueryTest.php',
                'tests/Feature/Reporting/ReportIndexControllerTest.php',
                'tests/Feature/Reporting/UserReportControllerTest.php',
                'tests/Feature/Reporting/AuditReportControllerTest.php',
                'tests/Feature/Reporting/ReportingLocalizationTest.php',
                'tests/Feature/Reporting/ReportingRemovalTest.php',
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function sharedFiles(): array
    {
        return match ($this) {
            self::Authorization => [
                'app/Models/User.php',
                'app/Providers/AppServiceProvider.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                'resources/js/types/auth.ts',
            ],
            self::Settings => [
                'routes/web.php',
                'app/Enums/Permission.php',
                'app/Http/Requests/UpdateSettingsRequest.php',
                'resources/js/layouts/settings/layout.tsx',
                'resources/js/types/index.ts',
                'tests/Unit/Enums/PermissionTest.php',
                'tests/Feature/InstallFeaturesCommandTest.php',
            ],
            self::UserManagement => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'app/Enums/Permission.php',
                'bootstrap/app.php',
                'routes/web.php',
                'resources/js/types/index.ts',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Enums/PermissionTest.php',
                'tests/Feature/InstallFeaturesCommandTest.php',
            ],
            self::Localization => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'bootstrap/app.php',
                'routes/web.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                'resources/js/types/global.d.ts',
                'resources/js/types/index.ts',
                'resources/js/components/app-header.tsx',
                'resources/js/components/app-sidebar-header.tsx',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Models/UserTest.php',
                'tests/Feature/InstallFeaturesCommandTest.php',
            ],
            self::Notifications => [
                'app/Actions/UpdateUserPassword.php',
                'app/Actions/Users/ActivateUserAction.php',
                'app/Actions/Users/DeactivateUserAction.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                'app/Models/User.php',
                'routes/web.php',
                'resources/js/components/app-header.tsx',
                'resources/js/components/app-sidebar-header.tsx',
                'resources/js/layouts/settings/layout.tsx',
                'resources/js/types/global.d.ts',
                'resources/js/types/index.ts',
                'tests/Feature/InstallFeaturesCommandTest.php',
            ],
            self::AuditTrails => [
                'app/Enums/Permission.php',
                'app/Actions/Users/CreateUserAction.php',
                'app/Actions/Users/UpdateUserAction.php',
                'app/Actions/Users/ActivateUserAction.php',
                'app/Actions/Users/DeactivateUserAction.php',
                'app/Actions/Users/DeleteUserAction.php',
                'app/Actions/Users/ChangeUserPasswordAction.php',
                'app/Actions/UpdateSettings.php',
                'routes/web.php',
                'resources/js/types/index.ts',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Enums/PermissionTest.php',
                'tests/Feature/InstallFeaturesCommandTest.php',
            ],
            self::Reporting => [
                'app/Enums/Permission.php',
                'routes/web.php',
                'resources/js/types/index.ts',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Enums/PermissionTest.php',
                'tests/Feature/InstallFeaturesCommandTest.php',
            ],
        };
    }
}
