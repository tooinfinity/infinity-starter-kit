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
                    'reporting',
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
     * @return array{auth_features: list<string>, optional_modules: list<string>}
     */
    public function answers(): array
    {
        return self::answersFor($this->name);
    }

    /**
     * Returns the matching installer preset name.
     */
    public function presetName(): string
    {
        return match (self::canonicalName($this->name)) {
            self::MINIMAL => Preset::MINIMAL,
            self::STANDARD => Preset::STANDARD,
            self::FULL => Preset::FULL,
            default => $this->name,
        };
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

    public function hasReporting(): bool
    {
        return $this->hasFeature('reporting');
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
        $paths = [
            'app/Models/User.php',
            'app/Http/Controllers/UserProfileController.php',
            'routes/web.php',
        ];

        if ($this->hasAuthorization()) {
            $paths[] = 'config/permission.php';
            $paths[] = 'app/Enums/Permission.php';
            $paths[] = 'app/Enums/Role.php';
            $paths[] = 'app/Console/Commands/SetupAuthorizationCommand.php';
        }

        if ($this->hasSettings()) {
            $paths[] = 'app/Models/Setting.php';
            $paths[] = 'app/Http/Controllers/SettingController.php';
            $paths[] = 'resources/js/pages/settings/application/edit.tsx';
        }

        if ($this->hasUserManagement()) {
            $paths[] = 'app/Http/Controllers/Users/UserController.php';
            $paths[] = 'resources/js/pages/users/index.tsx';
        }

        if ($this->hasNotifications()) {
            $paths[] = 'app/Models/NotificationPreference.php';
            $paths[] = 'app/Http/Controllers/NotificationController.php';
            $paths[] = 'resources/js/pages/notifications/index.tsx';
        }

        if ($this->hasAuditTrails()) {
            $paths[] = 'app/Models/AuditTrail.php';
            $paths[] = 'app/Http/Controllers/AuditTrails/AuditTrailController.php';
            $paths[] = 'resources/js/pages/audit-trails/index.tsx';
        }

        if ($this->hasReporting()) {
            $paths[] = 'app/Http/Controllers/Reporting/ReportIndexController.php';
            $paths[] = 'resources/js/pages/reports/index.tsx';
        }

        if ($this->hasReporting() || $this->hasUserManagement()) {
            $paths[] = 'config/data.php';
        }

        if ($this->hasLocalization()) {
            $paths[] = 'app/Http/Middleware/HandleLocale.php';
        }

        if ($this->hasTwoFactor()) {
            $paths[] = 'app/Http/Controllers/UserTwoFactorAuthenticationController.php';
        }

        return $paths;
    }

    /**
     * Files that must be pruned / absent in a generated application for this profile.
     *
     * @return list<string>
     */
    public function expectedAbsentPaths(): array
    {
        $paths = [];

        if (! $this->hasAuthorization()) {
            $paths[] = 'config/permission.php';
            $paths[] = 'app/Console/Commands/SetupAuthorizationCommand.php';
            $paths[] = 'resources/js/hooks/use-authorization.ts';
        }

        if (! $this->hasSettings()) {
            $paths[] = 'app/Models/Setting.php';
            $paths[] = 'app/Http/Controllers/SettingController.php';
            $paths[] = 'resources/js/pages/settings/application/edit.tsx';
        }

        if (! $this->hasUserManagement()) {
            $paths[] = 'app/Http/Controllers/Users/UserController.php';
            $paths[] = 'resources/js/pages/users';
        }

        if (! $this->hasNotifications()) {
            $paths[] = 'app/Models/NotificationPreference.php';
            $paths[] = 'app/Http/Controllers/NotificationController.php';
            $paths[] = 'resources/js/pages/notifications';
        }

        if (! $this->hasAuditTrails()) {
            $paths[] = 'app/Models/AuditTrail.php';
            $paths[] = 'app/Http/Controllers/AuditTrails';
            $paths[] = 'resources/js/pages/audit-trails';
        }

        if (! $this->hasReporting()) {
            $paths[] = 'app/Http/Controllers/Reporting';
            $paths[] = 'resources/js/pages/reports';
        }

        if (! $this->hasReporting() && ! $this->hasUserManagement()) {
            $paths[] = 'config/data.php';
        }

        if (! $this->hasLocalization()) {
            $paths[] = 'app/Http/Middleware/HandleLocale.php';
        }

        if (! $this->hasTwoFactor()) {
            $paths[] = 'app/Http/Controllers/UserTwoFactorAuthenticationController.php';
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function expectedPresentTables(): array
    {
        $tables = ['users'];

        if ($this->hasAuthorization()) {
            $tables[] = 'roles';
            $tables[] = 'permissions';
        }

        if ($this->hasSettings()) {
            $tables[] = 'settings';
        }

        if ($this->hasNotifications()) {
            $tables[] = 'notifications';
            $tables[] = 'notification_preferences';
        }

        if ($this->hasAuditTrails()) {
            $tables[] = 'audit_trails';
        }

        return $tables;
    }

    /**
     * @return list<string>
     */
    public function expectedAbsentTables(): array
    {
        $tables = [];

        if (! $this->hasAuthorization()) {
            $tables[] = 'roles';
            $tables[] = 'permissions';
        }

        if (! $this->hasSettings()) {
            $tables[] = 'settings';
        }

        if (! $this->hasNotifications()) {
            $tables[] = 'notifications';
            $tables[] = 'notification_preferences';
        }

        if (! $this->hasAuditTrails()) {
            $tables[] = 'audit_trails';
        }

        return $tables;
    }

    /**
     * @return array<string, list<string>>
     */
    public function expectedPresentColumns(): array
    {
        $columns = ['users' => ['email', 'password']];

        if ($this->hasTwoFactor()) {
            $columns['users'][] = 'two_factor_secret';
        }

        if ($this->hasLocalization()) {
            $columns['users'][] = 'locale';
        }

        return $columns;
    }

    /**
     * @return array<string, list<string>>
     */
    public function expectedAbsentColumns(): array
    {
        $columns = ['users' => []];

        if (! $this->hasTwoFactor()) {
            $columns['users'][] = 'two_factor_secret';
        }

        if (! $this->hasLocalization()) {
            $columns['users'][] = 'locale';
        }

        return $columns;
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

        if (! $this->hasReporting()) {
            $tokens[] = 'ReportIndexController';
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
}
