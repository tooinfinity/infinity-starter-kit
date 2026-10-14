<?php

declare(strict_types=1);

namespace App\Chisel\Features;

use App\Chisel\FeatureDefinition;

/**
 * Authoritative feature definitions for authentication modules.
 */
final class AuthFeatures
{
    /** @var array<string, FeatureDefinition>|null */
    private static ?array $definitions = null;

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function all(): array
    {
        return self::$definitions ??= [
            'login' => new FeatureDefinition(
                key: 'login',
                label: 'Login',
                sectionMarker: 'login',
                markerFiles: [
                    'config/fortify.php',
                    'routes/web.php',
                    'app/Providers/FortifyServiceProvider.php',
                    'resources/js/pages/welcome.tsx',
                    'resources/js/pages/user/create.tsx',
                ],
                exclusiveFiles: [
                    'app/Http/Controllers/LoginController.php',
                    'app/Http/Requests/CreateSessionRequest.php',
                    'resources/js/pages/session/create.tsx',
                    'tests/Feature/Controllers/LoginControllerTest.php',
                ],
                emptyDirectories: [
                    'resources/js/pages/session',
                ],
                dependencies: [],
            ),
            'logout' => new FeatureDefinition(
                key: 'logout',
                label: 'Logout',
                sectionMarker: 'logout',
                markerFiles: [
                    'routes/web.php',
                    'resources/js/components/user-menu-content.tsx',
                    'resources/js/pages/user-email-verification-notification/create.tsx',
                ],
                exclusiveFiles: [
                    'app/Http/Controllers/LogoutController.php',
                    'tests/Feature/Controllers/LogoutControllerTest.php',
                ],
                emptyDirectories: [],
                dependencies: ['login'],
            ),
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
                ],
                exclusiveFiles: [
                    'app/Actions/CreateUser.php',
                    'app/Http/Controllers/RegisterController.php',
                    'app/Http/Requests/CreateUserRequest.php',
                    'resources/js/pages/user/create.tsx',
                    'tests/Feature/Controllers/RegisterControllerTest.php',
                    'tests/Unit/Actions/CreateUserTest.php',
                ],
                emptyDirectories: [
                    'resources/js/pages/user',
                ],
                dependencies: ['login'],
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
                    'app/Data/Users/UserData.php',
                    'resources/js/types/users.ts',
                    'database/factories/UserFactory.php',
                    'database/migrations/0001_01_01_000000_create_users_table.php',
                    'tests/Unit/Models/UserTest.php',
                    'tests/Unit/Actions/UpdateUserTest.php',
                    'tests/Feature/Controllers/UserProfileControllerTest.php',
                    'tests/Feature/Users/UpdateUserTest.php',
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
                dependencies: ['login'],
            ),
            'password-reset' => new FeatureDefinition(
                key: 'password-reset',
                label: 'Password reset',
                sectionMarker: 'password-reset',
                markerFiles: [
                    'config/fortify.php',
                    'routes/web.php',
                    'app/Http/Controllers/UserPasswordController.php',
                    'resources/js/pages/session/create.tsx',
                ],
                exclusiveFiles: [
                    'app/Actions/CreateUserEmailResetNotification.php',
                    'app/Actions/CreateUserPassword.php',
                    'app/Http/Controllers/UserEmailResetNotificationController.php',
                    'app/Http/Requests/CreateUserEmailResetNotificationRequest.php',
                    'app/Http/Requests/CreateUserPasswordRequest.php',
                    'resources/js/pages/user-email-reset-notification/create.tsx',
                    'resources/js/pages/user-password/create.tsx',
                    'tests/Feature/Controllers/PasswordResetTest.php',
                    'tests/Feature/Controllers/UserEmailResetNotificationTest.php',
                    'tests/Unit/Actions/CreateUserEmailResetNotificationTest.php',
                    'tests/Unit/Actions/CreateUserPasswordTest.php',
                ],
                emptyDirectories: [
                    'resources/js/pages/user-email-reset-notification',
                ],
                dependencies: ['login'],
            ),
            'password-update' => new FeatureDefinition(
                key: 'password-update',
                label: 'Password update',
                sectionMarker: 'password-update',
                markerFiles: [
                    'routes/web.php',
                    'app/Http/Controllers/UserPasswordController.php',
                    'resources/js/layouts/settings/layout.tsx',
                ],
                exclusiveFiles: [
                    'app/Actions/UpdateUserPassword.php',
                    'app/Http/Requests/UpdateUserPasswordRequest.php',
                    'resources/js/pages/user-password/edit.tsx',
                    'tests/Feature/Controllers/PasswordUpdateTest.php',
                    'tests/Unit/Actions/UpdateUserPasswordTest.php',
                ],
                emptyDirectories: [],
                dependencies: ['login'],
            ),
            'two-factor' => new FeatureDefinition(
                key: 'two-factor',
                label: 'Two-factor authentication',
                sectionMarker: 'two-factor',
                markerFiles: [
                    'config/fortify.php',
                    'routes/web.php',
                    'app/Providers/FortifyServiceProvider.php',
                    'app/Http/Controllers/LoginController.php',
                    'resources/js/layouts/settings/layout.tsx',
                    'resources/js/types/auth.ts',
                    'app/Models/User.php',
                    'database/factories/UserFactory.php',
                    'database/migrations/0001_01_01_000000_create_users_table.php',
                    'tests/Unit/Models/UserTest.php',
                    'tests/Feature/Controllers/LoginControllerTest.php',
                    'tests/Feature/Users/InactiveUserAuthTest.php',
                    'tests/Feature/AuditTrails/RecordAuditTrailTest.php',
                ],
                exclusiveFiles: [
                    'app/Http/Controllers/UserTwoFactorAuthenticationController.php',
                    'app/Http/Requests/ShowUserTwoFactorAuthenticationRequest.php',
                    'resources/js/pages/user-password-confirmation/create.tsx',
                    'resources/js/pages/user-two-factor-authentication/show.tsx',
                    'resources/js/pages/user-two-factor-authentication-challenge/show.tsx',
                    'resources/js/components/two-factor-setup-modal.tsx',
                    'resources/js/components/two-factor-recovery-codes.tsx',
                    'resources/js/hooks/use-two-factor-auth.ts',
                    'tests/Feature/Controllers/UserTwoFactorAuthenticationControllerTest.php',
                ],
                emptyDirectories: [
                    'resources/js/pages/user-password-confirmation',
                    'resources/js/pages/user-two-factor-authentication',
                    'resources/js/pages/user-two-factor-authentication-challenge',
                ],
                dependencies: ['login'],
            ),
        ];
    }
}
