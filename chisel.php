<?php

declare(strict_types=1);

require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';

use App\Support\Chisel\ModuleRemover;
use App\Support\Chisel\ModuleResolver;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;

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

        // 2. Apply Fortify authentication sub-features transformations
        $authFeatures = (array) ($answers['auth_features'] ?? ['registration', 'email-verification', 'two-factor-authentication']);

        // Registration
        if (in_array('registration', $authFeatures, true)) {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'resources/js/pages/session/create.tsx',
                'resources/js/pages/welcome.tsx',
            )->removeSectionMarkers('registration');
        } else {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/pages/session/create.tsx',
                'resources/js/pages/welcome.tsx',
            )->removeSection('registration');
        }

        // Email Verification
        if (in_array('email-verification', $authFeatures, true)) {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
            )->removeSectionMarkers('email-verification');
        } else {
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
            )->delete();
        }

        // Two-Factor Authentication
        if (in_array('two-factor-authentication', $authFeatures, true)) {
            $chisel->files(
                'config/fortify.php',
                'routes/web.php',
                'app/Providers/FortifyServiceProvider.php',
                'resources/js/layouts/settings/layout.tsx',
                'app/Models/User.php',
                'database/factories/UserFactory.php',
                'database/migrations/0001_01_01_000000_create_users_table.php',
            )->removeSectionMarkers('two-factor-authentication');
        } else {
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
        }
    });
