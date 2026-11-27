<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use InvalidArgumentException;

/**
 * Authoritative feature profiles for generated application smoke testing and preset resolution.
 */
final readonly class Profile
{
    public const string MINIMAL = 'minimal';

    public const string STANDARD = 'standard';

    public const string DEFAULT = 'default';

    public const string FULL = 'full';

    public function __construct(
        public string $name,
    ) {}

    /**
     * @return array{auth_features: list<string>, optional_modules: list<string>}
     */
    public static function answersFor(string $name): array
    {
        return match (self::canonicalName($name)) {
            self::MINIMAL => [
                'auth_features' => [
                    'login',
                    'logout',
                ],
                'optional_modules' => [],
            ],
            self::STANDARD => [
                'auth_features' => [
                    'login',
                    'logout',
                    'registration',
                    'email-verification',
                    'password-reset',
                    'password-update',
                ],
                'optional_modules' => [
                    'authorization',
                    'settings',
                    'user-management',
                    'notifications',
                ],
            ],
            self::FULL => [
                'auth_features' => [
                    'login',
                    'logout',
                    'registration',
                    'email-verification',
                    'password-reset',
                    'password-update',
                    'two-factor',
                ],
                'optional_modules' => [
                    'authorization',
                    'settings',
                    'user-management',
                    'localization',
                    'notifications',
                    'audit-trails',
                ],
            ],
            default => throw new InvalidArgumentException(sprintf(
                'Unknown profile [%s]. Available profiles: %s.',
                $name,
                implode(', ', self::names()),
            )),
        };
    }

    /**
     * Resolve a profile by name or throw an InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     */
    public static function fromName(string $name): self
    {
        $normalized = self::normalize($name);

        if (! in_array($normalized, self::names(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown profile [%s]. Available profiles: %s.',
                $name,
                implode(', ', self::names()),
            ));
        }

        return new self($normalized);
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return array_map(fn (string $name): self => new self($name), self::names());
    }

    /**
     * The representative profiles tested by smoke verification.
     *
     * @return list<self>
     */
    public static function representative(): array
    {
        return [
            new self(self::MINIMAL),
            new self(self::DEFAULT),
            new self(self::FULL),
        ];
    }

    /**
     * All recognized profile names.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            self::MINIMAL,
            self::DEFAULT,
            self::STANDARD,
            self::FULL,
        ];
    }

    /**
     * @param  list<string>  $selectedFeatures
     * @return list<string>
     */
    public static function expectedPresentPathsFor(array $selectedFeatures): array
    {
        $has = static fn (string $feature): bool => in_array($feature, $selectedFeatures, true);

        $paths = [
            'app/Models/User.php',
            'app/Http/Controllers/UserProfileController.php',
            'routes/web.php',
        ];

        if ($has('authorization')) {
            $paths[] = 'config/permission.php';
            $paths[] = 'app/Enums/Permission.php';
            $paths[] = 'app/Enums/Role.php';
            $paths[] = 'app/Console/Commands/SetupAuthorizationCommand.php';
            $paths[] = 'app/Console/Commands/CreateAdminCommand.php';
        }

        if ($has('settings')) {
            $paths[] = 'app/Models/Setting.php';
            $paths[] = 'app/Http/Controllers/SettingController.php';
            $paths[] = 'resources/js/pages/settings/application/edit.tsx';
            $paths[] = 'routes/settings.php';
        }

        if ($has('user-management')) {
            $paths[] = 'app/Http/Controllers/Users/UserController.php';
            $paths[] = 'resources/js/pages/users/index.tsx';
            $paths[] = 'routes/user-management.php';
            $paths[] = 'config/data.php';
        }

        if ($has('notifications')) {
            $paths[] = 'app/Models/NotificationPreference.php';
            $paths[] = 'app/Http/Controllers/NotificationController.php';
            $paths[] = 'resources/js/pages/notifications/index.tsx';
            $paths[] = 'routes/notifications.php';
        }

        if ($has('audit-trails')) {
            $paths[] = 'config/activitylog.php';
            $paths[] = 'config/audit.php';
            $paths[] = 'database/migrations/2026_11_20_000000_create_activity_log_table.php';
            $paths[] = 'app/Models/Activity.php';
            $paths[] = 'app/Http/Controllers/AuditTrails/AuditTrailController.php';
            $paths[] = 'resources/js/pages/audit-trails/index.tsx';
            $paths[] = 'routes/audit-trails.php';
        }

        if ($has('localization')) {
            $paths[] = 'app/Http/Middleware/HandleLocale.php';
            $paths[] = 'app/Http/Controllers/LocaleController.php';
            $paths[] = 'routes/localization.php';
        }

        if ($has('two-factor')) {
            $paths[] = 'app/Http/Controllers/UserTwoFactorAuthenticationController.php';
        }

        return $paths;
    }

    /**
     * @param  list<string>  $selectedFeatures
     * @return list<string>
     */
    public static function expectedAbsentPathsFor(array $selectedFeatures): array
    {
        $has = static fn (string $feature): bool => in_array($feature, $selectedFeatures, true);

        $paths = [];

        if (! $has('authorization')) {
            $paths[] = 'config/permission.php';
            $paths[] = 'app/Console/Commands/SetupAuthorizationCommand.php';
            $paths[] = 'app/Console/Commands/CreateAdminCommand.php';
            $paths[] = 'resources/js/hooks/use-authorization.ts';
        }

        if (! $has('settings')) {
            $paths[] = 'app/Models/Setting.php';
            $paths[] = 'app/Http/Controllers/SettingController.php';
            $paths[] = 'resources/js/pages/settings/application/edit.tsx';
        }

        if (! $has('user-management')) {
            $paths[] = 'app/Http/Controllers/Users/UserController.php';
            $paths[] = 'resources/js/pages/users';
            $paths[] = 'config/data.php';
        }

        if (! $has('notifications')) {
            $paths[] = 'app/Models/NotificationPreference.php';
            $paths[] = 'app/Http/Controllers/NotificationController.php';
            $paths[] = 'resources/js/pages/notifications';
        }

        if (! $has('audit-trails')) {
            $paths[] = 'config/activitylog.php';
            $paths[] = 'config/audit.php';
            $paths[] = 'database/migrations/2026_11_20_000000_create_activity_log_table.php';
            $paths[] = 'app/Models/Activity.php';
            $paths[] = 'app/Http/Controllers/AuditTrails';
            $paths[] = 'resources/js/pages/audit-trails';
        }

        if (! $has('localization')) {
            $paths[] = 'app/Http/Middleware/HandleLocale.php';
            $paths[] = 'app/Http/Controllers/LocaleController.php';
        }

        if (! $has('two-factor')) {
            $paths[] = 'app/Http/Controllers/UserTwoFactorAuthenticationController.php';
        }

        return $paths;
    }

    /**
     * @param  list<string>  $selectedFeatures
     * @return list<string>
     */
    public static function expectedPresentTablesFor(array $selectedFeatures): array
    {
        $has = static fn (string $feature): bool => in_array($feature, $selectedFeatures, true);

        $tables = ['users'];

        if ($has('authorization')) {
            $tables[] = 'roles';
            $tables[] = 'permissions';
        }

        if ($has('settings')) {
            $tables[] = 'settings';
        }

        if ($has('notifications')) {
            $tables[] = 'notifications';
            $tables[] = 'notification_preferences';
        }

        if ($has('audit-trails')) {
            $tables[] = 'activity_log';
        }

        return $tables;
    }

    /**
     * @param  list<string>  $selectedFeatures
     * @return list<string>
     */
    public static function expectedAbsentTablesFor(array $selectedFeatures): array
    {
        $has = static fn (string $feature): bool => in_array($feature, $selectedFeatures, true);

        $tables = [];

        if (! $has('authorization')) {
            $tables[] = 'roles';
            $tables[] = 'permissions';
        }

        if (! $has('settings')) {
            $tables[] = 'settings';
        }

        if (! $has('notifications')) {
            $tables[] = 'notifications';
            $tables[] = 'notification_preferences';
        }

        if (! $has('audit-trails')) {
            $tables[] = 'activity_log';
        }

        return $tables;
    }

    /**
     * @param  list<string>  $selectedFeatures
     * @return array<string, list<string>>
     */
    public static function expectedPresentColumnsFor(array $selectedFeatures): array
    {
        $has = static fn (string $feature): bool => in_array($feature, $selectedFeatures, true);

        $columns = ['users' => ['email', 'password']];

        if ($has('two-factor')) {
            $columns['users'][] = 'two_factor_secret';
        }

        if ($has('localization')) {
            $columns['users'][] = 'locale';
        }

        return $columns;
    }

    /**
     * @param  list<string>  $selectedFeatures
     * @return array<string, list<string>>
     */
    public static function expectedAbsentColumnsFor(array $selectedFeatures): array
    {
        $has = static fn (string $feature): bool => in_array($feature, $selectedFeatures, true);

        $columns = ['users' => []];

        if (! $has('two-factor')) {
            $columns['users'][] = 'two_factor_secret';
        }

        if (! $has('localization')) {
            $columns['users'][] = 'locale';
        }

        return $columns;
    }

    /**
     * @return array{auth_features: list<string>, optional_modules: list<string>}
     */
    public function answers(): array
    {
        return self::answersFor($this->name);
    }

    public function hasFeature(string $feature): bool
    {
        $answers = $this->answers();

        return in_array($feature, $answers['auth_features'], true)
            || in_array($feature, $answers['optional_modules'], true);
    }

    public function hasAuthorization(): bool
    {
        return $this->hasFeature('authorization');
    }

    public function hasTwoFactor(): bool
    {
        return $this->hasFeature('two-factor');
    }

    public function hasAuditTrails(): bool
    {
        return $this->hasFeature('audit-trails');
    }

    public function hasNotifications(): bool
    {
        return $this->hasFeature('notifications');
    }

    public function hasLocalization(): bool
    {
        return $this->hasFeature('localization');
    }

    public function hasUserManagement(): bool
    {
        return $this->hasFeature('user-management');
    }

    public function hasSettings(): bool
    {
        return $this->hasFeature('settings');
    }

    /**
     * Files that must be present in a generated application for this profile.
     *
     * @return list<string>
     */
    public function expectedPresentPaths(): array
    {
        return self::expectedPresentPathsFor($this->selectedFeatures());
    }

    /**
     * Files that must be pruned / absent in a generated application for this profile.
     *
     * @return list<string>
     */
    public function expectedAbsentPaths(): array
    {
        return self::expectedAbsentPathsFor($this->selectedFeatures());
    }

    /**
     * @return list<string>
     */
    public function expectedPresentTables(): array
    {
        return self::expectedPresentTablesFor($this->selectedFeatures());
    }

    /**
     * @return list<string>
     */
    public function expectedAbsentTables(): array
    {
        return self::expectedAbsentTablesFor($this->selectedFeatures());
    }

    /**
     * @return array<string, list<string>>
     */
    public function expectedPresentColumns(): array
    {
        return self::expectedPresentColumnsFor($this->selectedFeatures());
    }

    /**
     * @return array<string, list<string>>
     */
    public function expectedAbsentColumns(): array
    {
        return self::expectedAbsentColumnsFor($this->selectedFeatures());
    }

    /**
     * @return list<string>
     */
    public function expectedForbiddenTokens(): array
    {
        $tokens = [];

        if (! $this->hasAuditTrails()) {
            $tokens[] = 'AuditTrail';
        }

        if (! $this->hasNotifications()) {
            $tokens[] = 'NotificationPreference';
        }

        if (! $this->hasLocalization()) {
            $tokens[] = 'HandleLocale';
        }

        return $tokens;
    }

    private static function canonicalName(string $name): string
    {
        $normalized = self::normalize($name);

        return $normalized === self::DEFAULT ? self::STANDARD : $normalized;
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(mb_trim($name));
    }

    /**
     * @return list<string>
     */
    private function selectedFeatures(): array
    {
        $answers = $this->answers();

        return array_merge($answers['auth_features'], $answers['optional_modules']);
    }
}
