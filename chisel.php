<?php

declare(strict_types=1);

require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';

use App\Support\Chisel\ModuleRemover;
use App\Support\Chisel\ModuleResolver;
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
        then: function (Chisel $chisel): void {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'resources/js/pages/session/create.tsx',
                'resources/js/pages/welcome.tsx',
                'app/Http/Controllers/UserController.php',
            )->removeSectionMarkers('registration');
        },
        else: function (Chisel $chisel): void {
            $chisel->php('app/Http/Controllers/UserController.php')
                ->removeImport('App\\Actions\\CreateUser')
                ->removeImport('App\\Http\\Requests\\CreateUserRequest');

            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/pages/session/create.tsx',
                'resources/js/pages/welcome.tsx',
                'app/Http/Controllers/UserController.php',
            )->removeSection('registration');

            $chisel->files(
                'app/Actions/CreateUser.php',
                'app/Http/Requests/CreateUserRequest.php',
                'resources/js/pages/user/create.tsx',
                'tests/Feature/Controllers/RegistrationTest.php',
                'tests/Unit/Actions/CreateUserTest.php',
            )->delete();

            ModuleRemover::pruneEmptyDirectories(__DIR__, [
                'resources/js/pages/user',
            ]);
        },
    )
    ->selected(
        'auth_features',
        'email-verification',
        then: function (Chisel $chisel): void {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
            )->removeSectionMarkers('email-verification');
        },
        else: function (Chisel $chisel): void {
            $chisel->php('app/Models/User.php')
                ->removeImport('Illuminate\\Contracts\\Auth\\MustVerifyEmail')
                ->removeInterface('MustVerifyEmail');

            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
            )->removeSection('email-verification');

            $chisel->files(
                'app/Actions/CreateUserEmailVerificationNotification.php',
                'app/Http/Controllers/UserEmailVerificationController.php',
                'app/Http/Controllers/UserEmailVerificationNotificationController.php',
                'app/Http/Requests/UpdateEmailVerificationRequest.php',
                'resources/js/pages/user-email-verification-notification/create.tsx',
                'tests/Feature/Controllers/UserEmailVerificationNotificationControllerTest.php',
                'tests/Feature/Controllers/UserEmailVerificationTest.php',
                'tests/Unit/Actions/CreateUserEmailVerificationNotificationTest.php',
            )->delete();

            ModuleRemover::pruneEmptyDirectories(__DIR__, [
                'resources/js/pages/user-email-verification-notification',
            ]);
        },
    )
    ->selected(
        'auth_features',
        'two-factor-authentication',
        then: function (Chisel $chisel): void {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/layouts/settings/layout.tsx',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
            )->removeSectionMarkers('two-factor-authentication');
        },
        else: function (Chisel $chisel): void {
            $chisel->php('app/Models/User.php')
                ->removeImport('Laravel\\Fortify\\TwoFactorAuthenticatable')
                ->removeTrait('TwoFactorAuthenticatable');

            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'resources/js/layouts/settings/layout.tsx',
                'app/Providers/FortifyServiceProvider.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
            )->removeSection('two-factor-authentication');

            $chisel->files(
                'app/Http/Controllers/UserTwoFactorAuthenticationController.php',
                'app/Http/Requests/ShowUserTwoFactorAuthenticationRequest.php',
                'resources/js/pages/user-two-factor-authentication/show.tsx',
                'resources/js/pages/user-two-factor-authentication-challenge/show.tsx',
                'tests/Feature/Controllers/UserTwoFactorAuthenticationControllerTest.php',
            )->delete();

            ModuleRemover::pruneEmptyDirectories(__DIR__, [
                'resources/js/pages/user-two-factor-authentication',
                'resources/js/pages/user-two-factor-authentication-challenge',
            ]);
        },
    )
    ->apply(function (Chisel $chisel, array $answers): void {
        $directory = __DIR__;

        // 1. Resolve and apply optional modules transformations
        $resolvedModules = ModuleResolver::resolve($answers);
        $unselectedModules = ModuleResolver::unselected($answers);

        // Remove unselected modules in reverse topological order
        foreach ($unselectedModules as $module) {
            ModuleRemover::remove($module, $chisel, $directory, $resolvedModules);
        }

        // Strip section markers from retained modules
        foreach ($resolvedModules as $module) {
            ModuleRemover::stripMarkers($module, $chisel, $directory);
        }

        // 2. Regenerate Wayfinder typed routes and actions (pruning stale files and directories)
        if (file_exists($directory.'/artisan')) {
            chiselRun([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form', '--no-interaction'], 'Generate Wayfinder Resources');
        }

        // 3. Format PHP codebase with Pint
        if (file_exists($directory.'/vendor/bin/pint')) {
            chiselRun(['vendor/bin/pint', '--format', 'agent'], 'Format PHP Code');
        }
    });
