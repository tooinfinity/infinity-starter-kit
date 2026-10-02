<?php

declare(strict_types=1);

namespace App\Chisel;

use InvalidArgumentException;

/**
 * Authoritative registry of all starter kit features, dependencies, and installer metadata.
 */
final class FeatureRegistry
{
    /** @var array<string, FeatureDefinition>|null */
    private static ?array $authFeatures = null;

    /** @var array<string, FeatureDefinition>|null */
    private static ?array $optionalModules = null;

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function authFeatures(): array
    {
        return self::$authFeatures ??= [
            'registration' => new FeatureDefinition(
                key: 'registration',
                label: 'Registration',
                sectionMarker: 'registration',
                markerFiles: [
                    'config/fortify.php',
                    'routes/web.php',
                    'app/Providers/FortifyServiceProvider.php',
                    'resources/js/pages/session/create.tsx',
                    'resources/js/pages/welcome.tsx',
                    'app/Http/Controllers/UserController.php',
                ],
                exclusiveFiles: [
                    'app/Actions/CreateUser.php',
                    'app/Http/Requests/CreateUserRequest.php',
                    'resources/js/pages/user/create.tsx',
                    'tests/Feature/Controllers/RegistrationTest.php',
                    'tests/Unit/Actions/CreateUserTest.php',
                ],
                emptyDirectories: [
                    'resources/js/pages/user',
                ],
            ),
            'email-verification' => new FeatureDefinition(
                key: 'email-verification',
                label: 'Email verification',
                sectionMarker: 'email-verification',
                markerFiles: [
                    'config/fortify.php',
                    'routes/web.php',
                    'app/Providers/FortifyServiceProvider.php',
                    'app/Http/Controllers/UserProfileController.php',
                    'app/Actions/UpdateUser.php',
                    'resources/js/pages/user-profile/edit.tsx',
                    'resources/js/types/auth.ts',
                    'app/Models/User.php',
                    'app/Actions/Users/UpdateUserAction.php',
                    'app/Http/Controllers/Users/UserController.php',
                    'resources/js/types/users.ts',
                    'database/factories/UserFactory.php',
                    'database/migrations/0001_01_01_000000_create_users_table.php',
                    'tests/Unit/Models/UserTest.php',
                    'tests/Unit/Actions/UpdateUserTest.php',
                    'tests/Feature/Controllers/UserProfileControllerTest.php',
                ],
                exclusiveFiles: [
                    'app/Actions/CreateUserEmailVerificationNotification.php',
                    'app/Http/Controllers/UserEmailVerificationController.php',
                    'app/Http/Controllers/UserEmailVerificationNotificationController.php',
                    'app/Http/Requests/UpdateEmailVerificationRequest.php',
                    'resources/js/pages/user-email-verification-notification/create.tsx',
                    'tests/Feature/Controllers/UserEmailVerificationNotificationControllerTest.php',
                    'tests/Feature/Controllers/UserEmailVerificationTest.php',
                    'tests/Unit/Actions/CreateUserEmailVerificationNotificationTest.php',
                ],
                emptyDirectories: [
                    'resources/js/pages/user-email-verification-notification',
                ],
            ),
            'two-factor-authentication' => new FeatureDefinition(
                key: 'two-factor-authentication',
                label: 'Two-factor authentication',
                sectionMarker: 'two-factor-authentication',
                markerFiles: [
                    'config/fortify.php',
                    'routes/web.php',
                    'app/Providers/FortifyServiceProvider.php',
                    'app/Http/Controllers/SessionController.php',
                    'resources/js/layouts/settings/layout.tsx',
                    'resources/js/types/auth.ts',
                    'app/Models/User.php',
                    'database/factories/UserFactory.php',
                    'database/migrations/0001_01_01_000000_create_users_table.php',
                    'tests/Unit/Models/UserTest.php',
                    'tests/Feature/Controllers/SessionControllerTest.php',
                    'tests/Feature/Users/InactiveUserAuthTest.php',
                    'tests/Feature/AuditTrails/RecordAuditTrailTest.php',
                ],
                exclusiveFiles: [
                    'app/Http/Controllers/UserTwoFactorAuthenticationController.php',
                    'app/Http/Requests/ShowUserTwoFactorAuthenticationRequest.php',
                    'resources/js/pages/user-two-factor-authentication/show.tsx',
                    'resources/js/pages/user-two-factor-authentication-challenge/show.tsx',
                    'resources/js/components/two-factor-setup-modal.tsx',
                    'resources/js/components/two-factor-recovery-codes.tsx',
                    'resources/js/hooks/use-two-factor-auth.ts',
                    'tests/Feature/Controllers/UserTwoFactorAuthenticationControllerTest.php',
                ],
                emptyDirectories: [
                    'resources/js/pages/user-two-factor-authentication',
                    'resources/js/pages/user-two-factor-authentication-challenge',
                ],
            ),
        ];
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function optionalModules(): array
    {
        return self::$optionalModules ??= [
            'authorization' => new FeatureDefinition(
                key: 'authorization',
                label: 'Authorization',
                sectionMarker: 'roles-permissions',
                markerFiles: [
                    'app/Models/User.php',
                    'app/Providers/AppServiceProvider.php',
                    'app/Http/Middleware/HandleInertiaRequests.php',
                    'resources/js/components/nav-main.tsx',
                    'routes/web.php',
                    'resources/js/types/auth.ts',
                ],
                exclusiveFiles: [
                    'config/permission.php',
                    'database/migrations/2026_01_01_000002_create_permission_tables.php',
                    'app/Enums/Permission.php',
                    'app/Enums/Role.php',
                    'app/Console/Commands/SetupAuthorizationCommand.php',
                    'app/Console/Commands/SetupAdminUserCommand.php',
                    'resources/js/components/can.tsx',
                    'resources/js/hooks/use-authorization.ts',
                    'tests/Feature/Authorization/SpatieRbacTest.php',
                    'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
                    'tests/Unit/Enums/PermissionTest.php',
                    'tests/Unit/Enums/RoleTest.php',
                ],
                emptyDirectories: [
                    'tests/Feature/Authorization',
                ],
                composerPackages: [
                    'spatie/laravel-permission',
                ],
            ),
            'settings' => new FeatureDefinition(
                key: 'settings',
                label: 'Settings',
                sectionMarker: 'settings',
                markerFiles: [
                    'routes/web.php',
                    'app/Enums/Permission.php',
                    'resources/js/layouts/settings/layout.tsx',
                    'resources/js/types/index.ts',
                    'tests/Unit/Enums/PermissionTest.php',
                ],
                exclusiveFiles: [
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
                emptyDirectories: [
                    'resources/js/pages/settings/application',
                    'tests/Feature/Settings',
                ],
                dependencies: [
                    'authorization',
                ],
            ),
            'user-management' => new FeatureDefinition(
                key: 'user-management',
                label: 'User Management',
                sectionMarker: 'user-management',
                markerFiles: [
                    'database/migrations/0001_01_01_000000_create_users_table.php',
                    'app/Models/User.php',
                    'app/Http/Requests/CreateSessionRequest.php',
                    'database/factories/UserFactory.php',
                    'app/Enums/Permission.php',
                    'bootstrap/app.php',
                    'routes/web.php',
                    'resources/js/types/index.ts',
                    'resources/js/components/app-sidebar.tsx',
                    'tests/Unit/Models/UserTest.php',
                    'tests/Unit/Enums/PermissionTest.php',
                ],
                exclusiveFiles: [
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
                emptyDirectories: [
                    'app/Actions/Users',
                    'app/Data/Users',
                    'app/Http/Controllers/Users',
                    'app/Http/Requests/Users',
                    'app/Queries/Users',
                    'resources/js/components/users',
                    'resources/js/pages/users',
                    'tests/Feature/Users',
                ],
                dependencies: [
                    'authorization',
                ],
            ),
            'localization' => new FeatureDefinition(
                key: 'localization',
                label: 'Localization',
                sectionMarker: 'localization',
                markerFiles: [
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
                    'tests/Unit/Models/UserTest.php',
                ],
                exclusiveFiles: [
                    'app/Actions/ChangeLocale.php',
                    'app/Actions/ResolveLocale.php',
                    'app/Enums/Locale.php',
                    'app/Http/Controllers/LocaleController.php',
                    'app/Http/Middleware/HandleLocale.php',
                    'app/Http/Requests/ChangeLocaleRequest.php',
                    'resources/js/components/language-selector.tsx',
                    'resources/js/types/localization.ts',
                    'lang/fr/common.php',
                    'lang/fr/localization.php',
                    'lang/fr/notifications.php',
                    'lang/fr/reports.php',
                    'lang/fr/audit.php',
                    'lang/ar/common.php',
                    'lang/ar/localization.php',
                    'lang/ar/notifications.php',
                    'lang/ar/reports.php',
                    'lang/ar/audit.php',
                    'tests/Unit/Enums/LocaleTest.php',
                    'tests/Feature/Localization/ChangeLocaleTest.php',
                    'tests/Feature/Localization/InertiaLocalePropsTest.php',
                    'tests/Feature/Localization/LocaleMiddlewareTest.php',
                    'tests/Feature/Localization/ResolveLocaleTest.php',
                    'tests/Feature/Localization/TranslationFileTest.php',
                ],
                emptyDirectories: [
                    'tests/Feature/Localization',
                    'lang/fr',
                    'lang/ar',
                ],
                composerPackages: [
                    'erag/laravel-lang-sync-inertia',
                ],
                frontendPackages: [
                    '@erag/lang-sync-inertia',
                ],
            ),
            'notifications' => new FeatureDefinition(
                key: 'notifications',
                label: 'Notifications',
                sectionMarker: 'notifications',
                markerFiles: [
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
                ],
                exclusiveFiles: [
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
                    'tests/Feature/Notifications/NotificationPreferencesTest.php',
                    'tests/Feature/Notifications/NotificationSecurityTest.php',
                    'tests/Unit/Enums/NotificationTypeTest.php',
                    'tests/Unit/Notifications/PasswordChangedTest.php',
                    'tests/Unit/Actions/Notifications/DeleteReadNotificationsTest.php',
                ],
                emptyDirectories: [
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
            ),
            'audit-trails' => new FeatureDefinition(
                key: 'audit-trails',
                label: 'Audit Trails',
                sectionMarker: 'audit-trails',
                markerFiles: [
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
                ],
                exclusiveFiles: [
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
                    'tests/Feature/AuditTrails/AuditTrailSecurityTest.php',
                    'tests/Feature/AuditTrails/AuditTrailTransactionTest.php',
                    'tests/Feature/AuditTrails/RecordAuditTrailTest.php',
                    'tests/Unit/Enums/AuditEventEnumTest.php',
                ],
                emptyDirectories: [
                    'app/Actions/AuditTrails',
                    'app/Http/Controllers/AuditTrails',
                    'app/Http/Requests/AuditTrails',
                    'app/Queries/AuditTrails',
                    'resources/js/components/audit-trails',
                    'resources/js/pages/audit-trails',
                    'tests/Feature/AuditTrails',
                ],
                dependencies: [
                    'authorization',
                ],
            ),
            'reporting' => new FeatureDefinition(
                key: 'reporting',
                label: 'Reporting',
                sectionMarker: 'reporting',
                markerFiles: [
                    'app/Enums/Permission.php',
                    'routes/web.php',
                    'resources/js/types/index.ts',
                    'resources/js/components/app-sidebar.tsx',
                    'tests/Unit/Enums/PermissionTest.php',
                ],
                exclusiveFiles: [
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
                ],
                emptyDirectories: [
                    'app/Data/Reporting',
                    'app/Queries/Reporting',
                    'app/Http/Requests/Reporting',
                    'app/Http/Controllers/Reporting',
                    'resources/js/components/reports',
                    'resources/js/pages/reports',
                    'tests/Unit/Reporting',
                    'tests/Feature/Reporting',
                ],
                dependencies: [
                    'audit-trails',
                    'user-management',
                    'authorization',
                ],
            ),
        ];
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function all(): array
    {
        return array_merge(self::authFeatures(), self::optionalModules());
    }

    public static function get(string $key): FeatureDefinition
    {
        $all = self::all();

        if (! isset($all[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown feature key [%s].', $key));
        }

        return $all[$key];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function dependencies(): array
    {
        return [
            'reporting' => ['audit-trails', 'user-management', 'authorization'],
            'audit-trails' => ['authorization'],
            'user-management' => ['authorization'],
            'settings' => ['authorization'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allComposerPackages(): array
    {
        $packages = ['laravel/chisel', 'spatie/laravel-data'];
        foreach (self::all() as $feature) {
            foreach ($feature->composerPackages as $pkg) {
                $packages[] = $pkg;
            }
        }

        return array_values(array_unique($packages));
    }

    /**
     * @return list<string>
     */
    public static function allFrontendPackages(): array
    {
        $packages = [];
        foreach (self::all() as $feature) {
            foreach ($feature->frontendPackages as $pkg) {
                $packages[] = $pkg;
            }
        }

        return array_values(array_unique($packages));
    }

    /**
     * @return list<array{features: list<string>, files: list<string>}>
     */
    public static function crossFeatureTests(): array
    {
        return [
            [
                'features' => ['audit-trails', 'settings'],
                'files' => ['tests/Feature/AuditTrails/SettingsAuditingTest.php'],
            ],
            [
                'features' => ['audit-trails', 'user-management'],
                'files' => ['tests/Feature/AuditTrails/UserAuditingTest.php'],
            ],
            [
                'features' => ['notifications', 'localization'],
                'files' => ['tests/Feature/Notifications/NotificationLocalizationTest.php'],
            ],
            [
                'features' => ['reporting', 'localization'],
                'files' => ['tests/Feature/Reporting/ReportingLocalizationTest.php'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function cleanupFiles(): array
    {
        return [
            'app/Console/Commands/InstallFeaturesCommand.php',
            'app/Console/Commands/SetupAuthorizationCommand.php',
            'app/Console/Commands/SetupAdminUserCommand.php',
            'chisel.php',
            'chisel-paths.php',
            'app/Chisel/FeatureDefinition.php',
            'app/Chisel/FeatureRegistry.php',
            'app/Chisel/Installer/DependencyValidator.php',
            'app/Chisel/Installer/ComposerSynchronizer.php',
            'app/Chisel/Installer/FrontendPackagePruner.php',
            'app/Chisel/Installer/DirectoryPruner.php',
            'app/Chisel/Installer/ConfigCleaner.php',
            'app/Chisel/Installer/Cleanup.php',
            'app/Chisel/Installer/FeaturePruner.php',
            'tests/Unit/Chisel/DirectoryPruningTest.php',
            'tests/Unit/Chisel/JsonPruningTest.php',
            'tests/Unit/Chisel/FeatureRegistryTest.php',
            'tests/Unit/Chisel/DependencyValidatorTest.php',
            'tests/Unit/Chisel/ComposerSynchronizerTest.php',
            'tests/Feature/Chisel/DependencyValidationTest.php',
            'tests/Feature/Chisel/FeatureCombinationTest.php',
            'tests/Feature/Chisel/GeneratedProjectLifecycleTest.php',
            'tests/Feature/Chisel/ComposerCreateProjectLifecycleTest.php',
            'tests/Feature/Chisel/InstallFeaturesCommandTest.php',
            'tests/Feature/Chisel/MarkerIntegrityTest.php',
            'tests/Feature/Chisel/RegistryIntegrityTest.php',
            'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function cleanupDirectories(): array
    {
        return [
            'app/Chisel/Installer',
            'app/Chisel',
            'tests/Unit/Chisel',
            'tests/Feature/Chisel',
        ];
    }

    /**
     * Transform registry into the exact shape expected by chisel-paths.php and backward-compatible tests.
     *
     * @return array<string, mixed>
     */
    public static function toPathsArray(): array
    {
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
                'extra_lang_files' => [
                    'lang/fr/common.php',
                    'lang/fr/localization.php',
                    'lang/fr/notifications.php',
                    'lang/fr/reports.php',
                    'lang/fr/audit.php',
                    'lang/ar/common.php',
                    'lang/ar/localization.php',
                    'lang/ar/notifications.php',
                    'lang/ar/reports.php',
                    'lang/ar/audit.php',
                ],
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
                'files' => [
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
                    'tests/Unit/Reporting/ReportTypeTest.php',
                    'tests/Unit/Reporting/ReportCategoryTest.php',
                    'tests/Feature/Reporting/UserReportQueryTest.php',
                    'tests/Feature/Reporting/AuditReportQueryTest.php',
                    'tests/Feature/Reporting/ReportIndexControllerTest.php',
                    'tests/Feature/Reporting/UserReportControllerTest.php',
                    'tests/Feature/Reporting/AuditReportControllerTest.php',
                    'tests/Feature/Reporting/ReportingLocalizationTest.php',
                ],
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

            'data' => [
                'config' => 'config/data.php',
                'composer_package' => 'spatie/laravel-data',
            ],

            'dependencies' => self::dependencies(),

            'cross_feature_tests' => self::crossFeatureTests(),

            'chisel' => [
                'files' => self::cleanupFiles(),
                'empty_dirs' => self::cleanupDirectories(),
            ],
        ];
    }
}
