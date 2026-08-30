<?php

declare(strict_types=1);

require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';

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
            name: 'authorization_features',
            label: 'Which authorization features would you like to enable?',
            options: [
                'roles-permissions' => 'Spatie Roles & Permissions (spatie/laravel-permission)',
            ],
            default: ['roles-permissions'],
            hint: 'Use space to select, enter to confirm.',
        ),
    ])
    ->selected(
        'auth_features',
        'registration',
        then: fn (Chisel $chisel) => $chisel->files(
            'config/fortify.php',
            'routes/web.php',
            'app/Providers/FortifyServiceProvider.php',
            'app/Models/User.php',
            'database/factories/UserFactory.php',
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'resources/js/pages/session/create.tsx',
            'resources/js/pages/welcome.tsx',
        )->removeSectionMarkers('registration'),
        else: fn (Chisel $chisel) => $chisel->files(
            'config/fortify.php',
            'routes/web.php',
            'app/Providers/FortifyServiceProvider.php',
            'resources/js/pages/session/create.tsx',
            'resources/js/pages/welcome.tsx',
        )->removeSection('registration'),
    )
    ->selected(
        'auth_features',
        'email-verification',
        then: fn (Chisel $chisel) => $chisel->files(
            'config/fortify.php',
            'routes/web.php',
            'app/Providers/FortifyServiceProvider.php',
            'app/Models/User.php',
            'database/factories/UserFactory.php',
            'database/migrations/0001_01_01_000000_create_users_table.php',
        )->removeSectionMarkers('email-verification'),
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
            )->delete();
        },
    )
    ->selected(
        'auth_features',
        'two-factor-authentication',
        then: fn (Chisel $chisel) => $chisel->files(
            'config/fortify.php',
            'routes/web.php',
            'app/Providers/FortifyServiceProvider.php',
            'resources/js/layouts/settings/layout.tsx',
            'app/Models/User.php',
            'database/factories/UserFactory.php',
            'database/migrations/0001_01_01_000000_create_users_table.php',
        )->removeSectionMarkers('two-factor-authentication'),
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
        },
    )
    ->selected(
        'authorization_features',
        'roles-permissions',
        then: fn (Chisel $chisel) => $chisel->files(
            'app/Models/User.php',
            'app/Providers/AppServiceProvider.php',
            'app/Http/Middleware/HandleInertiaRequests.php',
            'resources/js/types/auth.ts',
            'tests/Feature/Authorization/SpatieRbacTest.php',
            'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
            'tests/Unit/Enums/PermissionTest.php',
            'tests/Unit/Enums/RoleTest.php',
            'resources/js/hooks/use-authorization.ts',
            'resources/js/components/can.tsx',
        )->removeSectionMarkers('roles-permissions'),
        else: function (Chisel $chisel): void {
            $chisel->php('app/Models/User.php')
                ->removeImport('Spatie\\Permission\\Traits\\HasRoles')
                ->removeTrait('HasRoles');
            $chisel->files(
                'app/Models/User.php',
                'app/Providers/AppServiceProvider.php',
                'app/Http/Middleware/HandleInertiaRequests.php',
                'resources/js/types/auth.ts',
            )->removeSection('roles-permissions');
            $chisel->files(
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
            )->delete();
        },
    );
