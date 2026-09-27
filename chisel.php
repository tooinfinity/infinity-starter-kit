<?php

declare(strict_types=1);

if (! class_exists('Laravel\Chisel\Chisel')) {
    require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';
}

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
    function chiselRun(array $command, string $label, ?string $cwd = null): void
    {
        $directory = $cwd ?? __DIR__;

        if (defined('PHPUNIT_COMPOSER_INSTALL') || class_exists('PHPUnit\Framework\TestCase', false) || (function_exists('app') && method_exists(app(), 'runningUnitTests') && app()->runningUnitTests())) {
            return;
        }

        if (! class_exists(Process::class)) {
            return;
        }

        $process = task(
            label: $label,
            keepSummary: true,
            callback: function (Logger $logger) use ($command, $directory) {
                $process = new Process($command, $directory);
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

if (! function_exists('chiselDirectory')) {
    function chiselDirectory(Chisel $chisel): string
    {
        static $extractor;
        $extractor ??= Closure::bind(fn (Chisel $c): string => $c->directory, null, Chisel::class);

        return $extractor($chisel);
    }
}

if (! function_exists('chiselSkipsNode')) {
    function chiselSkipsNode(): bool
    {
        if (defined('PHPUNIT_COMPOSER_INSTALL') || class_exists('PHPUnit\Framework\TestCase', false) || (function_exists('app') && method_exists(app(), 'runningUnitTests') && app()->runningUnitTests())) {
            return true;
        }

        return filter_var(
            $_ENV['LARAVEL_INSTALLER_NO_NODE']
                ?? $_SERVER['LARAVEL_INSTALLER_NO_NODE']
                ?? getenv('LARAVEL_INSTALLER_NO_NODE'),
            FILTER_VALIDATE_BOOL,
        );
    }
}

if (! function_exists('chiselRemoveFrontendPackages')) {
    function chiselRemoveFrontendPackages(string $directory, Chisel $c, string ...$packages): void
    {
        if (! chiselSkipsNode()) {
            try {
                $c->npm()->remove(...$packages);

                return;
            } catch (Throwable) {
                // Fall back to safe JSON removal if package manager is unavailable
            }
        }

        $packageJsonPath = $directory.'/package.json';
        if (! file_exists($packageJsonPath)) {
            return;
        }

        /** @var array<string, mixed> $packageData */
        $packageData = json_decode((string) file_get_contents($packageJsonPath), true, 512, JSON_THROW_ON_ERROR);

        foreach ($packages as $package) {
            unset(
                $packageData['dependencies'][$package],
                $packageData['devDependencies'][$package],
                $packageData['optionalDependencies'][$package],
                $packageData['peerDependencies'][$package],
            );
        }

        file_put_contents(
            $packageJsonPath,
            json_encode($packageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }
}

if (! function_exists('chiselRemoveComposerPackages')) {
    function chiselRemoveComposerPackages(string $directory, string ...$packages): void
    {
        $composerJsonPath = $directory.'/composer.json';
        if (! file_exists($composerJsonPath)) {
            return;
        }

        /** @var array<string, mixed> $composerData */
        $composerData = json_decode((string) file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

        foreach ($packages as $package) {
            unset(
                $composerData['require'][$package],
                $composerData['require-dev'][$package],
            );
        }

        file_put_contents(
            $composerJsonPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }
}

if (! function_exists('chiselCleanComposerPostCreate')) {
    function chiselCleanComposerPostCreate(string $directory): void
    {
        $composerJsonPath = $directory.'/composer.json';
        if (! file_exists($composerJsonPath)) {
            return;
        }

        /** @var array<string, mixed> $composerData */
        $composerData = json_decode((string) file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);

        unset(
            $composerData['require']['laravel/chisel'],
            $composerData['require-dev']['laravel/chisel'],
        );

        if (isset($composerData['scripts']['post-create-project-cmd']) && is_array($composerData['scripts']['post-create-project-cmd'])) {
            $composerData['scripts']['post-create-project-cmd'] = array_values(array_filter(
                $composerData['scripts']['post-create-project-cmd'],
                fn (mixed $cmd): bool => is_string($cmd) && ! str_contains($cmd, 'install:features'),
            ));
        }

        file_put_contents(
            $composerJsonPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }
}

if (! function_exists('chiselPruneEmptyDirectories')) {
    /**
     * @param  list<string>  $paths
     */
    function chiselPruneEmptyDirectories(string $directory, array $paths): void
    {
        $baseRoot = realpath($directory) ?: $directory;
        $dirs = array_values(array_unique(array_filter($paths, fn (string $p): bool => $p !== '' && $p !== '.')));
        usort($dirs, fn (string $a, string $b): int => mb_substr_count($b, '/') <=> mb_substr_count($a, '/'));

        $protectedRootDirs = [
            'app',
            'bootstrap',
            'config',
            'database',
            'lang',
            'public',
            'resources',
            'routes',
            'storage',
            'tests',
        ];

        foreach ($dirs as $relPath) {
            $current = mb_trim($relPath, '/');

            while ($current !== '' && $current !== '.') {
                if (in_array($current, $protectedRootDirs, true) || ! str_contains($current, '/')) {
                    break;
                }

                $fullPath = $directory.'/'.$current;
                $realPath = realpath($fullPath);

                if ($realPath === false || ! is_dir($realPath)) {
                    $current = dirname($current);

                    continue;
                }

                if ($realPath === $baseRoot || ! str_starts_with($realPath, $baseRoot.'/')) {
                    break;
                }

                $items = scandir($realPath);
                if ($items !== false && count($items) === 2) {
                    @rmdir($realPath);
                    $current = dirname($current);
                } else {
                    break;
                }
            }
        }
    }
}

if (! function_exists('chiselValidateDependencies')) {
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, list<string>>  $dependencyMap
     */
    function chiselValidateDependencies(array $answers, array $dependencyMap): void
    {
        $optionalModules = (array) ($answers['optional_modules'] ?? []);

        foreach ($dependencyMap as $feature => $required) {
            if (in_array($feature, $optionalModules, true)) {
                $missing = array_diff($required, $optionalModules);
                if ($missing !== []) {
                    throw new RuntimeException(sprintf(
                        'The "%s" module requires the following module(s): %s.',
                        $feature,
                        implode(', ', $missing),
                    ));
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
 *         two_factor_files: list<string>,
 *         settings_layout: string,
 *         profile: string,
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
 *         extra_lang_files?: list<string>,
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
 *         files?: list<string>,
 *         empty_dirs: list<string>,
 *     },
 *     dependencies?: array<string, list<string>>,
 *     cross_feature_tests?: list<array{features: list<string>, files: list<string>}>,
 * } $paths
 */
$paths = require __DIR__.'/chisel-paths.php';

$script = Chisel::script(__DIR__)
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
                'settings' => 'Settings',
                'user-management' => 'User Management',
                'localization' => 'Localization',
                'notifications' => 'Notifications',
                'audit-trails' => 'Audit Trails',
                'reporting' => 'Reporting',
            ],
            default: [
                'authorization',
                'settings',
                'user-management',
                'localization',
                'notifications',
                'audit-trails',
                'reporting',
            ],
            hint: 'Use space to select, enter to confirm.',
        ),
    ])
    ->apply(function (Chisel $chisel, array $answers) use ($paths): void {
        chiselValidateDependencies($answers, $paths['dependencies'] ?? []);
    })
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

            chiselPruneEmptyDirectories(chiselDirectory($chisel), [
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
                $paths['auth']['profile'],
                $paths['auth']['auth_types'],
                'app/Models/User.php',
                'app/Actions/Users/UpdateUserAction.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'tests/Unit/Models/UserTest.php',
            )->removeSectionMarkers('email-verification');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->php('app/Models/User.php')
                ->removeInterface('MustVerifyEmail');

            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                $paths['auth']['profile'],
                $paths['auth']['auth_types'],
                'app/Models/User.php',
                'app/Actions/Users/UpdateUserAction.php',
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

            chiselPruneEmptyDirectories(chiselDirectory($chisel), [
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
                'app/Http/Controllers/SessionController.php',
                $paths['auth']['settings_layout'],
                $paths['auth']['auth_types'],
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'tests/Unit/Models/UserTest.php',
            )->removeSectionMarkers('two-factor-authentication');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Http/Controllers/SessionController.php',
                $paths['auth']['settings_layout'],
                $paths['auth']['auth_types'],
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'tests/Unit/Models/UserTest.php',
            )->removeSection('two-factor-authentication');

            $chisel->files(...[
                'app/Http/Controllers/UserTwoFactorAuthenticationController.php',
                'app/Http/Requests/ShowUserTwoFactorAuthenticationRequest.php',
                $paths['auth']['two_factor_show'],
                $paths['auth']['two_factor_challenge'],
                ...$paths['auth']['two_factor_files'],
                'tests/Feature/Controllers/UserTwoFactorAuthenticationControllerTest.php',
            ])->delete();

            chiselPruneEmptyDirectories(chiselDirectory($chisel), $paths['auth']['two_factor_dirs']);
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
                'resources/js/components/nav-main.tsx',
                'routes/web.php',
                $paths['auth']['auth_types'],
            )->removeSectionMarkers('roles-permissions');
        },
        else: function (Chisel $chisel) use ($paths): void {
            $chisel->files(
                'app/Models/User.php',
                'app/Providers/AppServiceProvider.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                'resources/js/components/nav-main.tsx',
                'routes/web.php',
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

            chiselRemoveComposerPackages(chiselDirectory($chisel), $paths['authorization']['composer_package']);

            chiselPruneEmptyDirectories(chiselDirectory($chisel), $paths['authorization']['empty_dirs']);
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

            chiselPruneEmptyDirectories(chiselDirectory($chisel), $paths['settings']['empty_dirs']);
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

            chiselPruneEmptyDirectories(chiselDirectory($chisel), $paths['user_management']['empty_dirs']);
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
            $chisel->php('app/Models/User.php')
                ->removeInterface('HasLocalePreference');

            $chisel->file('app/Models/User.php')
                ->removeLinesContaining('@property-read Locale|null $locale');

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
                ...$paths['localization']['components'],
                $paths['localization']['types'],
                ...($paths['localization']['extra_lang_files'] ?? []),
                'tests/Unit/Enums/LocaleTest.php',
                'tests/Feature/Localization/ChangeLocaleTest.php',
                'tests/Feature/Localization/InertiaLocalePropsTest.php',
                'tests/Feature/Localization/LocaleMiddlewareTest.php',
                'tests/Feature/Localization/ResolveLocaleTest.php',
                'tests/Feature/Localization/TranslationFileTest.php',
            ])->delete();

            $dir = chiselDirectory($chisel);
            chiselRemoveComposerPackages($dir, $paths['localization']['composer_package']);
            chiselRemoveFrontendPackages($dir, $chisel, $paths['localization']['frontend_package']);

            chiselPruneEmptyDirectories($dir, $paths['localization']['empty_dirs']);
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
                'tests/Feature/Notifications/NotificationPreferencesTest.php',
                'tests/Feature/Notifications/NotificationSecurityTest.php',
                'tests/Unit/Enums/NotificationTypeTest.php',
                'tests/Unit/Notifications/PasswordChangedTest.php',
                'tests/Unit/Actions/Notifications/DeleteReadNotificationsTest.php',
            ])->delete();

            chiselPruneEmptyDirectories(chiselDirectory($chisel), $paths['notifications']['empty_dirs']);
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
                'tests/Unit/Enums/AuditEventEnumTest.php',
            ])->delete();

            chiselPruneEmptyDirectories(chiselDirectory($chisel), $paths['audit_trails']['empty_dirs']);
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
                ...($paths['reporting']['files'] ?? []),
                ...$paths['reporting']['components'],
                ...$paths['reporting']['pages'],
                $paths['reporting']['types'],
            ])->delete();

            chiselPruneEmptyDirectories(chiselDirectory($chisel), $paths['reporting']['empty_dirs']);
        },
    );

foreach ($paths['cross_feature_tests'] ?? [] as $entry) {
    $script->selectedAll(
        'optional_modules',
        $entry['features'],
        then: null,
        else: function (Chisel $chisel) use ($entry): void {
            $chisel->files(...$entry['files'])->delete();
        },
    );
}

return $script
    ->selectedAny(
        'optional_modules',
        ['user-management', 'reporting'],
        then: null,
        else: function (Chisel $chisel): void {
            chiselRemoveComposerPackages(chiselDirectory($chisel), 'spatie/laravel-data');
        },
    )
    ->apply(function (Chisel $chisel) use ($paths): void {
        $directory = chiselDirectory($chisel);

        chiselCleanComposerPostCreate($directory);

        if (file_exists($directory.'/vendor/bin/pint')) {
            chiselRun(['vendor/bin/pint', '--format', 'agent'], 'Format PHP Code', $directory);
        }

        if (file_exists($directory.'/artisan')) {
            chiselRun([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form', '--no-interaction'], 'Generate Wayfinder Resources', $directory);
        }

        if (! chiselSkipsNode()) {
            $chisel->npm()->run('lint');
        }

        $chiselFiles = $paths['chisel']['files'] ?? [
            'app/Console/Commands/InstallFeaturesCommand.php',
            'chisel.php',
            'chisel-paths.php',
        ];

        $chisel->files(...$chiselFiles)->delete();

        chiselPruneEmptyDirectories($directory, $paths['chisel']['empty_dirs'] ?? [
            'tests/Unit/Chisel',
            'tests/Feature/Chisel',
        ]);
    });
