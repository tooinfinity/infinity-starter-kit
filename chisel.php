<?php

declare(strict_types=1);

require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';

use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Prompts\Support\Logger;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\task;

if (! function_exists('chiselRun')) {
    /**
     * Run a console command with progress indicator.
     *
     * @param  list<string>  $command
     */
    function chiselRun(array $command, string $label): void
    {
        if (defined('PHPUNIT_COMPOSER_INSTALL') || class_exists('PHPUnit\Framework\TestCase', false)) {
            $process = new Process($command, __DIR__);
            $process->run();

            return;
        }

        if (! class_exists(Process::class)) {
            return;
        }

        $process = task(
            label: $label,
            keepSummary: true,
            callback: function (Logger $logger) use ($command) {
                $process = new Process($command, __DIR__);
                $process->run(function ($type, $line) use ($logger): void {
                    $logger->line($line);
                });

                if ($process->isSuccessful()) {
                    $logger->success(implode(' ', $command));

                    return $process;
                }

                $logger->error(implode(' ', $command));
                $logger->error('Error output: '.mb_trim($process->getErrorOutput()));
                $logger->error('Chisel: Your project may be in a partially-modified state — review the output above before continuing.');

                return $process;
            },
        );

        if (! $process->isSuccessful()) {
            exit($process->getExitCode());
        }
    }
}

if (! function_exists('chiselSkipsNode')) {
    function chiselSkipsNode(): bool
    {
        return filter_var(
            $_ENV['LARAVEL_INSTALLER_NO_NODE']
                ?? $_SERVER['LARAVEL_INSTALLER_NO_NODE']
                ?? getenv('LARAVEL_INSTALLER_NO_NODE'),
            FILTER_VALIDATE_BOOL,
        );
    }
}

if (! function_exists('chiselRemoveFrontendPackages')) {
    function chiselRemoveFrontendPackages(Chisel $c, string ...$packages): void
    {
        if (! chiselSkipsNode()) {
            $c->npm()->remove(...$packages);

            return;
        }

        foreach ($packages as $package) {
            $c->file('package.json')->removeLinesContaining('"'.$package.'":');
        }
    }
}

if (! function_exists('chiselPruneEmptyDirectories')) {
    /**
     * @param  list<string>  $paths
     */
    function chiselPruneEmptyDirectories(string $directory, array $paths): void
    {
        $dirs = array_values(array_unique(array_filter($paths, fn (string $p): bool => $p !== '' && $p !== '.')));
        usort($dirs, fn (string $a, string $b): int => mb_substr_count($b, '/') <=> mb_substr_count($a, '/'));

        $protected = [
            '',
            '.',
            'app',
            'app/Actions',
            'app/Console',
            'app/Console/Commands',
            'app/Data',
            'app/Enums',
            'app/Exceptions',
            'app/Http',
            'app/Http/Controllers',
            'app/Http/Middleware',
            'app/Http/Requests',
            'app/Models',
            'app/Providers',
            'app/Queries',
            'app/Rules',
            'app/Support',
            'bootstrap',
            'config',
            'database',
            'database/factories',
            'database/migrations',
            'database/seeders',
            'lang',
            'lang/en',
            'public',
            'resources',
            'resources/css',
            'resources/js',
            'resources/js/actions',
            'resources/js/components',
            'resources/js/components/ui',
            'resources/js/hooks',
            'resources/js/layouts',
            'resources/js/lib',
            'resources/js/pages',
            'resources/js/pages/settings',
            'resources/js/routes',
            'resources/js/types',
            'routes',
            'storage',
            'tests',
            'tests/Browser',
            'tests/Feature',
            'tests/Feature/Controllers',
            'tests/Unit',
            'tests/Unit/Actions',
            'tests/Unit/Middleware',
            'tests/Unit/Models',
            'tests/Unit/Rules',
            'tests/Unit/Services',
            'tests/Unit/Enums',
        ];

        foreach ($dirs as $relPath) {
            $current = mb_trim($relPath, '/');

            while ($current !== '' && ! in_array($current, $protected, true)) {
                $fullPath = $directory.'/'.$current;

                if (! is_dir($fullPath)) {
                    $current = dirname($current);
                    if ($current === '.') {
                        break;
                    }

                    continue;
                }

                $items = scandir($fullPath);
                if ($items !== false && count($items) === 2) {
                    @rmdir($fullPath);
                    $current = dirname($current);
                    if ($current === '.') {
                        break;
                    }
                } else {
                    break;
                }
            }
        }
    }
}

/**
 * Framework-specific filenames and paths are supplied by chisel-paths.php.
 *
 * @var array{
 *     auth: array{
 *         login: string,
 *         welcome: string,
 *         register: string,
 *         user_dir: string,
 *         verify_email: string,
 *         verify_email_dir: string,
 *         two_factor_show: string,
 *         two_factor_challenge: string,
 *         two_factor_dirs: list<string>,
 *         settings_layout: string,
 *         auth_types: string,
 *     },
 *     authorization: array{
 *         files: list<string>,
 *         composer_package: string,
 *         empty_dirs: list<string>,
 *     },
 *     settings: array{
 *         pages: list<string>,
 *         types: string,
 *         empty_dirs: list<string>,
 *     },
 *     user_management: array{
 *         components: list<string>,
 *         pages: list<string>,
 *         types: string,
 *         empty_dirs: list<string>,
 *     },
 *     localization: array{
 *         components: list<string>,
 *         types: string,
 *         composer_package: string,
 *         frontend_package: string,
 *         empty_dirs: list<string>,
 *     },
 *     notifications: array{
 *         components: list<string>,
 *         pages: list<string>,
 *         types: string,
 *         empty_dirs: list<string>,
 *     },
 *     audit_trails: array{
 *         components: list<string>,
 *         pages: list<string>,
 *         types: string,
 *         empty_dirs: list<string>,
 *     },
 *     reporting: array{
 *         components: list<string>,
 *         pages: list<string>,
 *         types: string,
 *         empty_dirs: list<string>,
 *     },
 * } $paths
 */
$paths = require __DIR__.'/chisel-paths.php';

return Chisel::script(__DIR__)
    ->questions([
        Question::multiselect(
            name: 'auth_features',
            label: 'Which authentication features would you like to enable?',
            options: [
                'registration' => 'Registration',
                'email-verification' => 'Email verification',
                'two-factor-authentication' => 'Two-factor authentication',
            ],
            default: ['registration', 'email-verification', 'two-factor-authentication'],
            hint: 'Use space to select, enter to confirm.',
        ),
        Question::multiselect(
            name: 'optional_modules',
            label: 'Which optional modules should be installed?',
            options: [
                'authorization' => 'Authorization',
                /* @chisel-settings */
                'settings' => 'Settings',
                /* @end-chisel-settings */
                /* @chisel-user-management */
                'user-management' => 'User Management',
                /* @end-chisel-user-management */
                /* @chisel-localization */
                'localization' => 'Localization',
                /* @end-chisel-localization */
                /* @chisel-notifications */
                'notifications' => 'Notifications',
                /* @end-chisel-notifications */
                /* @chisel-audit-trails */
                'audit-trails' => 'Audit Trails',
                /* @end-chisel-audit-trails */
                /* @chisel-reporting */
                'reporting' => 'Reporting',
                /* @end-chisel-reporting */
            ],
            default: [
                'authorization',
                /* @chisel-settings */
                'settings',
                /* @end-chisel-settings */
                /* @chisel-user-management */
                'user-management',
                /* @end-chisel-user-management */
                /* @chisel-localization */
                'localization',
                /* @end-chisel-localization */
                /* @chisel-notifications */
                'notifications',
                /* @end-chisel-notifications */
                /* @chisel-audit-trails */
                'audit-trails',
                /* @end-chisel-audit-trails */
                /* @chisel-reporting */
                'reporting',
                /* @end-chisel-reporting */
            ],
            hint: 'Use space to select, enter to confirm.',
        ),
    ])
    ->selected(
        'auth_features',
        'registration',
        then: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                $paths['auth']['login'],
                $paths['auth']['welcome'],
                'app/Http/Controllers/UserController.php',
            )->removeSectionMarkers('registration');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->php('app/Http/Controllers/UserController.php')
                ->removeImport('App\\Actions\\CreateUser')
                ->removeImport('App\\Http\\Requests\\CreateUserRequest');

            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                $paths['auth']['login'],
                $paths['auth']['welcome'],
                'app/Http/Controllers/UserController.php',
            )->removeSection('registration');

            $chisel->files(
                'app/Actions/CreateUser.php',
                'app/Http/Requests/CreateUserRequest.php',
                $paths['auth']['register'],
                'tests/Feature/Controllers/RegistrationTest.php',
                'tests/Unit/Actions/CreateUserTest.php',
            )->delete();

            chiselPruneEmptyDirectories(__DIR__, [
                $paths['auth']['user_dir'],
            ]);
        },
    )
    ->selected(
        'auth_features',
        'email-verification',
        then: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                $paths['auth']['auth_types'],
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'tests/Unit/Models/UserTest.php',
            )->removeSectionMarkers('email-verification');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->php('app/Models/User.php')
                ->removeImport('Illuminate\\Contracts\\Auth\\MustVerifyEmail')
                ->removeInterface('MustVerifyEmail');

            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                $paths['auth']['auth_types'],
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'tests/Unit/Models/UserTest.php',
            )->removeSection('email-verification');

            $chisel->files(
                'app/Actions/CreateUserEmailVerificationNotification.php',
                'app/Http/Controllers/UserEmailVerificationController.php',
                'app/Http/Controllers/UserEmailVerificationNotificationController.php',
                'app/Http/Requests/UpdateEmailVerificationRequest.php',
                $paths['auth']['verify_email'],
                'tests/Feature/Controllers/UserEmailVerificationNotificationControllerTest.php',
                'tests/Feature/Controllers/UserEmailVerificationTest.php',
                'tests/Unit/Actions/CreateUserEmailVerificationNotificationTest.php',
            )->delete();

            chiselPruneEmptyDirectories(__DIR__, [
                $paths['auth']['verify_email_dir'],
            ]);
        },
    )
    ->selected(
        'auth_features',
        'two-factor-authentication',
        then: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                $paths['auth']['settings_layout'],
                $paths['auth']['auth_types'],
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'tests/Unit/Models/UserTest.php',
            )->removeSectionMarkers('two-factor-authentication');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->php('app/Models/User.php')
                ->removeImport('Laravel\\Fortify\\TwoFactorAuthenticatable')
                ->removeTrait('TwoFactorAuthenticatable');

            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                $paths['auth']['settings_layout'],
                $paths['auth']['auth_types'],
                'app/Providers/FortifyServiceProvider.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'tests/Unit/Models/UserTest.php',
            )->removeSection('two-factor-authentication');

            $chisel->files(
                'app/Http/Controllers/UserTwoFactorAuthenticationController.php',
                'app/Http/Requests/ShowUserTwoFactorAuthenticationRequest.php',
                $paths['auth']['two_factor_show'],
                $paths['auth']['two_factor_challenge'],
                'tests/Feature/Controllers/UserTwoFactorAuthenticationControllerTest.php',
            )->delete();

            chiselPruneEmptyDirectories(__DIR__, $paths['auth']['two_factor_dirs']);
        },
    )
    ->selected(
        'optional_modules',
        'authorization',
        then: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'app/Models/User.php',
                'app/Providers/AppServiceProvider.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                $paths['auth']['auth_types'],
                'tests/Unit/Enums/PermissionTest.php',
            )->removeSectionMarkers('roles-permissions');
        },
        else: function (Chisel $chisel) use ($paths): void {
            if (file_exists(__DIR__.'/app/Models/User.php')) {
                $chisel->php('app/Models/User.php')
                    ->removeImport('Spatie\\Permission\\Traits\\HasRoles')
                    ->removeTrait('HasRoles');
            }

            $chisel->files(
                'app/Models/User.php',
                'app/Providers/AppServiceProvider.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                $paths['auth']['auth_types'],
            )->removeSection('roles-permissions');

            $chisel->files(...[
                'config/permission.php',
                'database/migrations/2026_01_01_000002_create_permission_tables.php',
                'app/Enums/Permission.php',
                'app/Enums/Role.php',
                'app/Console/Commands/SetupAuthorizationCommand.php',
                'app/Console/Commands/SetupAdminUserCommand.php',
                ...$paths['authorization']['files'],
                'tests/Feature/Authorization/SpatieRbacTest.php',
                'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
                'tests/Unit/Enums/PermissionTest.php',
                'tests/Unit/Enums/RoleTest.php',
            ])->delete();

            $chisel->file('composer.json')->removeLinesContaining('"'.$paths['authorization']['composer_package'].'":');

            chiselPruneEmptyDirectories(__DIR__, $paths['authorization']['empty_dirs']);
        },
    )
    ->selected(
        'optional_modules',
        'settings',
        then: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'routes/web.php',
                'app/Enums/Permission.php',
                $paths['auth']['settings_layout'],
                'resources/js/types/index.ts',
                'tests/Unit/Enums/PermissionTest.php',
            )->removeSectionMarkers('settings');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'routes/web.php',
                'app/Enums/Permission.php',
                $paths['auth']['settings_layout'],
                'resources/js/types/index.ts',
                'tests/Unit/Enums/PermissionTest.php',
            )->removeSection('settings');

            $chisel->files(...[
                'app/Enums/SettingKey.php',
                'app/Enums/SettingGroup.php',
                'app/Models/Setting.php',
                'app/Actions/GetSetting.php',
                'app/Actions/UpdateSettings.php',
                'app/Http/Controllers/SettingController.php',
                'app/Http/Requests/UpdateSettingsRequest.php',
                'database/factories/SettingFactory.php',
                'database/migrations/2026_01_01_000003_create_settings_table.php',
                ...$paths['settings']['pages'],
                $paths['settings']['types'],
                'tests/Unit/Models/SettingTest.php',
                'tests/Unit/Enums/SettingKeyTest.php',
                'tests/Unit/Enums/SettingGroupTest.php',
                'tests/Feature/Controllers/SettingControllerTest.php',
                'tests/Feature/Settings/SettingsActionTest.php',
            ])->delete();

            chiselPruneEmptyDirectories(__DIR__, $paths['settings']['empty_dirs']);
        },
    )
    ->selected(
        'optional_modules',
        'user-management',
        then: function (Chisel $chisel): void {
            $chisel->files(
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'app/Enums/Permission.php',
                'bootstrap/app.php',
                'routes/web.php',
                'resources/js/types/index.ts',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Models/UserTest.php',
                'tests/Unit/Enums/PermissionTest.php',
            )->removeSectionMarkers('user-management');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'app/Enums/Permission.php',
                'bootstrap/app.php',
                'routes/web.php',
                'resources/js/types/index.ts',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Models/UserTest.php',
                'tests/Unit/Enums/PermissionTest.php',
            )->removeSection('user-management');

            $chisel->files(...[
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
                ...$paths['user_management']['components'],
                ...$paths['user_management']['pages'],
                $paths['user_management']['types'],
                'tests/Feature/Users/UserListingTest.php',
                'tests/Feature/Users/CreateUserTest.php',
                'tests/Feature/Users/UpdateUserTest.php',
                'tests/Feature/Users/ActivateDeactivateUserTest.php',
                'tests/Feature/Users/ChangePasswordTest.php',
                'tests/Feature/Users/DeleteUserTest.php',
                'tests/Feature/Users/InactiveUserAuthTest.php',
                'tests/Feature/Users/SuperAdminProtectionTest.php',
                'tests/Feature/Users/UpdateUserRolesRequestTest.php',
            ])->delete();

            chiselPruneEmptyDirectories(__DIR__, $paths['user_management']['empty_dirs']);
        },
    )
    ->selected(
        'optional_modules',
        'localization',
        then: function (Chisel $chisel): void {
            $chisel->files(
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
            )->removeSectionMarkers('localization');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
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
            )->removeSection('localization');

            $chisel->files(...[
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
                ...$paths['localization']['components'],
                $paths['localization']['types'],
                'tests/Unit/Enums/LocaleTest.php',
                'tests/Feature/Localization/ChangeLocaleTest.php',
                'tests/Feature/Localization/InertiaLocalePropsTest.php',
                'tests/Feature/Localization/LocaleMiddlewareTest.php',
                'tests/Feature/Localization/ResolveLocaleTest.php',
                'tests/Feature/Localization/TranslationFileTest.php',
            ])->delete();

            $chisel->file('composer.json')->removeLinesContaining('"'.$paths['localization']['composer_package'].'":');
            chiselRemoveFrontendPackages($chisel, $paths['localization']['frontend_package']);

            chiselPruneEmptyDirectories(__DIR__, $paths['localization']['empty_dirs']);
        },
    )
    ->selected(
        'optional_modules',
        'notifications',
        then: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'app/Actions/UpdateUserPassword.php',
                'app/Actions/Users/ActivateUserAction.php',
                'app/Actions/Users/DeactivateUserAction.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                'app/Models/User.php',
                'routes/web.php',
                'resources/js/components/app-header.tsx',
                'resources/js/components/app-sidebar-header.tsx',
                $paths['auth']['settings_layout'],
                'resources/js/types/global.d.ts',
                'resources/js/types/index.ts',
            )->removeSectionMarkers('notifications');
        },
        else: function (Chisel $chisel) use ($paths): void {
            if (file_exists(__DIR__.'/app/Models/User.php')) {
                $chisel->php('app/Models/User.php')
                    ->removeImport('Illuminate\\Contracts\\Translation\\HasLocalePreference')
                    ->removeInterface('HasLocalePreference');
            }

            $chisel->files(
                'app/Actions/UpdateUserPassword.php',
                'app/Actions/Users/ActivateUserAction.php',
                'app/Actions/Users/DeactivateUserAction.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                'app/Models/User.php',
                'routes/web.php',
                'resources/js/components/app-header.tsx',
                'resources/js/components/app-sidebar-header.tsx',
                $paths['auth']['settings_layout'],
                'resources/js/types/global.d.ts',
                'resources/js/types/index.ts',
            )->removeSection('notifications');

            $chisel->files(...[
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
                ...$paths['notifications']['components'],
                ...$paths['notifications']['pages'],
                $paths['notifications']['types'],
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
            ])->delete();

            chiselPruneEmptyDirectories(__DIR__, $paths['notifications']['empty_dirs']);
        },
    )
    ->selected(
        'optional_modules',
        'audit-trails',
        then: function (Chisel $chisel): void {
            $chisel->files(
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
            )->removeSectionMarkers('audit-trails');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
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
            )->removeSection('audit-trails');

            $chisel->files(...[
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
                ...$paths['audit_trails']['components'],
                ...$paths['audit_trails']['pages'],
                $paths['audit_trails']['types'],
                'tests/Feature/AuditTrails/AuditTrailListingTest.php',
                'tests/Feature/AuditTrails/AuditTrailSecurityTest.php',
                'tests/Feature/AuditTrails/AuditTrailTransactionTest.php',
                'tests/Feature/AuditTrails/RecordAuditTrailTest.php',
                'tests/Feature/AuditTrails/SettingsAuditingTest.php',
                'tests/Feature/AuditTrails/UserAuditingTest.php',
                'tests/Unit/AuditEventEnumTest.php',
            ])->delete();

            chiselPruneEmptyDirectories(__DIR__, $paths['audit_trails']['empty_dirs']);
        },
    )
    ->selected(
        'optional_modules',
        'reporting',
        then: function (Chisel $chisel): void {
            $chisel->files(
                'app/Enums/Permission.php',
                'routes/web.php',
                'resources/js/types/index.ts',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Enums/PermissionTest.php',
            )->removeSectionMarkers('reporting');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'app/Enums/Permission.php',
                'routes/web.php',
                'resources/js/types/index.ts',
                'resources/js/components/app-sidebar.tsx',
                'tests/Unit/Enums/PermissionTest.php',
            )->removeSection('reporting');

            $chisel->files(...[
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
                ...$paths['reporting']['components'],
                ...$paths['reporting']['pages'],
                $paths['reporting']['types'],
                'tests/Unit/Reporting/ReportTypeTest.php',
                'tests/Unit/Reporting/ReportCategoryTest.php',
                'tests/Feature/Reporting/UserReportQueryTest.php',
                'tests/Feature/Reporting/AuditReportQueryTest.php',
                'tests/Feature/Reporting/ReportIndexControllerTest.php',
                'tests/Feature/Reporting/UserReportControllerTest.php',
                'tests/Feature/Reporting/AuditReportControllerTest.php',
                'tests/Feature/Reporting/ReportingLocalizationTest.php',
            ])->delete();

            chiselPruneEmptyDirectories(__DIR__, $paths['reporting']['empty_dirs']);
        },
    )
    ->selectedAny(
        'optional_modules',
        ['user-management', 'reporting'],
        then: null,
        else: function (Chisel $chisel): void {
            $chisel->file('composer.json')->removeLinesContaining('"spatie/laravel-data":');
        },
    )
    ->apply(function (Chisel $chisel): void {
        $directory = __DIR__;

        $chisel->file('composer.json')
            ->removeLinesContaining('"@php artisan install:features --ansi"')
            ->removeLinesContaining('"laravel/chisel":');

        if (file_exists($directory.'/vendor/bin/pint')) {
            chiselRun(['vendor/bin/pint', '--format', 'agent'], 'Format PHP Code');
        }

        if (file_exists($directory.'/artisan')) {
            chiselRun([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form', '--no-interaction'], 'Generate Wayfinder Resources');
        }

        if (! chiselSkipsNode()) {
            $chisel->npm()->run('lint');
        }

        $chisel->files(
            'app/Console/Commands/InstallFeaturesCommand.php',
            'chisel.php',
            'chisel-paths.php',
        )->delete();
    });
