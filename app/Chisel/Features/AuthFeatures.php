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
}
