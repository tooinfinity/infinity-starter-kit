<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureRegistry;
use InvalidArgumentException;

/**
 * Reusable, declarative scenario definition for installer test matrices.
 */
final readonly class InstallerScenario
{
    /**
     * @param  list<string>  $authFeatures
     * @param  list<string>  $optionalModules
     * @param  array{name: string, email: string, password: string}|null  $admin
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $env
     * @param  list<string>|null  $expectedDependencies
     * @param  list<string>|null  $expectedAbsentDependencies
     * @param  list<string>|null  $expectedRetainedPaths
     * @param  list<string>|null  $expectedRemovedPaths
     * @param  list<string>|null  $expectedPresentTables
     * @param  list<string>|null  $expectedAbsentTables
     * @param  array<string, list<string>>|null  $expectedPresentColumns
     * @param  array<string, list<string>>|null  $expectedAbsentColumns
     * @param  list<string>  $expectedForbiddenTokens
     * @param  list<string>  $expectedOutputContains
     * @param  list<string>  $expectedOutputNotContains
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $category,
        public array $authFeatures,
        public array $optionalModules,
        public ?array $admin = null,
        public array $options = [],
        public array $env = [],
        public int $expectedExitCode = 0,
        public ?array $expectedDependencies = null,
        public ?array $expectedAbsentDependencies = null,
        public ?array $expectedRetainedPaths = null,
        public ?array $expectedRemovedPaths = null,
        public ?array $expectedPresentTables = null,
        public ?array $expectedAbsentTables = null,
        public ?array $expectedPresentColumns = null,
        public ?array $expectedAbsentColumns = null,
        public array $expectedForbiddenTokens = [],
        public array $expectedOutputContains = [],
        public array $expectedOutputNotContains = [],
    ) {}

    // --------------------------------------------------------------------------
    // Predefined Scenarios Factory Methods
    // --------------------------------------------------------------------------

    public static function minimal(): self
    {
        $answers = Profile::answersFor(Profile::MINIMAL);

        return new self(
            id: 'minimal',
            name: 'Minimal Profile',
            description: 'Core session authentication only (login + logout), all optional modules pruned.',
            category: 'profile',
            authFeatures: $answers['auth_features'],
            optionalModules: $answers['optional_modules'],
        );
    }

    public static function default(): self
    {
        $answers = Profile::answersFor(Profile::DEFAULT);

        return new self(
            id: 'default',
            name: 'Default Profile',
            description: 'Standard authentication flow and recommended modules (authorization, settings, user-management, notifications).',
            category: 'profile',
            authFeatures: $answers['auth_features'],
            optionalModules: $answers['optional_modules'],
            admin: [
                'name' => 'Default Admin',
                'email' => 'admin@example.com',
                'password' => 'DefaultAdminSecret123!',
            ],
            expectedOutputNotContains: ['DefaultAdminSecret123!'],
        );
    }

    public static function full(): self
    {
        $answers = Profile::answersFor(Profile::FULL);

        return new self(
            id: 'full',
            name: 'Full Profile',
            description: 'All authentication capabilities (including 2FA) and all optional starter kit modules enabled.',
            category: 'profile',
            authFeatures: $answers['auth_features'],
            optionalModules: $answers['optional_modules'],
            admin: [
                'name' => 'Full Admin',
                'email' => 'full-admin@example.com',
                'password' => 'FullAdminSecret123!',
            ],
            expectedOutputNotContains: ['FullAdminSecret123!'],
        );
    }

    public static function authMinimal(): self
    {
        return new self(
            id: 'auth-minimal',
            name: 'Auth-Minimal Profile',
            description: 'Minimal auth (login + logout) combined with all compatible optional modules.',
            category: 'profile',
            authFeatures: ['login', 'logout'],
            optionalModules: [
                'authorization',
                'settings',
                'user-management',
                'localization',
                'notifications',
                'audit-trails',
            ],
        );
    }

    public static function noModules(): self
    {
        return new self(
            id: 'no-modules',
            name: 'No Optional Modules',
            description: 'Full authentication suite with all optional modules disabled.',
            category: 'profile',
            authFeatures: [
                'login',
                'logout',
                'registration',
                'email-verification',
                'password-reset',
                'password-update',
                'two-factor',
            ],
            optionalModules: [],
        );
    }

    public static function dependencyHeavy(): self
    {
        return new self(
            id: 'dependency-heavy',
            name: 'Dependency-Heavy Profile',
            description: 'Modules requiring Authorization: Audit Trails and User Management with Authorization.',
            category: 'profile',
            authFeatures: ['login', 'logout'],
            optionalModules: [
                'authorization',
                'user-management',
                'audit-trails',
            ],
        );
    }

    public static function moduleAuthorization(): self
    {
        return new self(
            id: 'module-authorization',
            name: 'Authorization Alone',
            description: 'Authorization module enabled in isolation with minimal auth.',
            category: 'module',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
        );
    }

    public static function moduleSettings(): self
    {
        return new self(
            id: 'module-settings',
            name: 'Settings Module',
            description: 'Settings module enabled with required dependency Authorization.',
            category: 'module',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization', 'settings'],
        );
    }

    public static function moduleUserManagement(): self
    {
        return new self(
            id: 'module-user-management',
            name: 'User Management Module',
            description: 'User Management module enabled with required dependency Authorization.',
            category: 'module',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization', 'user-management'],
        );
    }

    public static function moduleLocalization(): self
    {
        return new self(
            id: 'module-localization',
            name: 'Localization Module',
            description: 'Localization module enabled in isolation without dependency on authorization or user management.',
            category: 'module',
            authFeatures: ['login', 'logout'],
            optionalModules: ['localization'],
        );
    }

    public static function moduleNotifications(): self
    {
        return new self(
            id: 'module-notifications',
            name: 'Notifications Module',
            description: 'Notifications module enabled in isolation without dependency on authorization or user management.',
            category: 'module',
            authFeatures: ['login', 'logout'],
            optionalModules: ['notifications'],
        );
    }

    public static function moduleAuditTrails(): self
    {
        return new self(
            id: 'module-audit-trails',
            name: 'Audit Trails Module',
            description: 'Audit Trails module enabled with required dependency Authorization.',
            category: 'module',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization', 'audit-trails'],
        );
    }

    public static function authNoRegistration(): self
    {
        return new self(
            id: 'auth-no-registration',
            name: 'Registration Disabled',
            description: 'Full auth suite with registration disabled (invite-only or admin-provisioned setup).',
            category: 'auth',
            authFeatures: [
                'login',
                'logout',
                'email-verification',
                'password-reset',
                'password-update',
                'two-factor',
            ],
            optionalModules: ['authorization', 'user-management'],
        );
    }

    public static function authNoEmailVerification(): self
    {
        return new self(
            id: 'auth-no-email-verification',
            name: 'Email Verification Disabled',
            description: 'Auth suite with immediate access without email verification step.',
            category: 'auth',
            authFeatures: [
                'login',
                'logout',
                'registration',
                'password-reset',
                'password-update',
                'two-factor',
            ],
            optionalModules: ['authorization'],
        );
    }

    public static function authNoPasswordReset(): self
    {
        return new self(
            id: 'auth-no-password-reset',
            name: 'Password Reset Disabled',
            description: 'Auth suite without self-service password reset endpoints.',
            category: 'auth',
            authFeatures: [
                'login',
                'logout',
                'registration',
                'email-verification',
                'password-update',
                'two-factor',
            ],
            optionalModules: ['authorization'],
        );
    }

    public static function authNoTwoFactor(): self
    {
        return new self(
            id: 'auth-no-two-factor',
            name: 'Two-Factor Disabled',
            description: 'Auth suite with TOTP two-factor authentication pruned.',
            category: 'auth',
            authFeatures: [
                'login',
                'logout',
                'registration',
                'email-verification',
                'password-reset',
                'password-update',
            ],
            optionalModules: ['authorization'],
        );
    }

    public static function authInvalidDependency(): self
    {
        return new self(
            id: 'auth-invalid-dependency',
            name: 'Invalid Auth Dependency',
            description: 'Invalid input rejecting Registration when Login is unselected.',
            category: 'auth',
            authFeatures: ['registration'],
            optionalModules: [],
            expectedExitCode: 1,
            expectedOutputContains: ['requires the following feature(s): login'],
        );
    }

    public static function dbEmpty(): self
    {
        return new self(
            id: 'db-empty',
            name: 'Empty Database',
            description: 'Clean installation on an empty unmigrated database.',
            category: 'database',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
        );
    }

    public static function dbMigrated(): self
    {
        return new self(
            id: 'db-migrated',
            name: 'Existing Migrated Database',
            description: 'Installation against a pre-migrated database without data loss.',
            category: 'database',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
        );
    }

    public static function dbEarlyMigrationFresh(): self
    {
        return new self(
            id: 'db-early-migration-fresh',
            name: 'Early Migration with --fresh',
            description: 'Rebuilds database cleanly when migrations ran prior to feature installation.',
            category: 'database',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            options: ['--fresh' => true],
        );
    }

    public static function adminValidCredentials(): self
    {
        return new self(
            id: 'admin-valid-credentials',
            name: 'Admin with Valid Credentials',
            description: 'Authorization enabled with valid admin credentials created and password protected from logs/output.',
            category: 'admin',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            admin: [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => 'SecureAdminPass!12345',
            ],
            expectedOutputNotContains: ['SecureAdminPass!12345'],
        );
    }

    public static function adminNoCredentials(): self
    {
        return new self(
            id: 'admin-no-credentials',
            name: 'Authorization Without Admin Credentials',
            description: 'Authorization enabled without admin credentials: roles created with zero users.',
            category: 'admin',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
        );
    }

    public static function adminCliOptions(): self
    {
        return new self(
            id: 'admin-cli-options',
            name: 'Admin via CLI Options',
            description: 'Admin credentials supplied through --admin-name, --admin-email flags.',
            category: 'admin',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            admin: [
                'name' => 'CLI Admin',
                'email' => 'cli-admin@example.com',
                'password' => 'CliPassword123!',
            ],
            expectedOutputNotContains: ['CliPassword123!'],
        );
    }

    public static function adminAnswersPayload(): self
    {
        return new self(
            id: 'admin-answers-payload',
            name: 'Admin via Answers Payload',
            description: 'Admin credentials embedded directly within the answers JSON object.',
            category: 'admin',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            admin: [
                'name' => 'Payload Admin',
                'email' => 'payload-admin@example.com',
                'password' => 'PayloadPassword123!',
            ],
            expectedOutputNotContains: ['PayloadPassword123!'],
        );
    }

    public static function failurePreflight(): self
    {
        return new self(
            id: 'failure-preflight',
            name: 'Failure at Preflight Phase',
            description: 'Deterministic preflight check failure halting before any destructive pruning mutations.',
            category: 'failure',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            env: ['CHISEL_TEST_FAIL_STAGE' => 'preflight'],
            expectedExitCode: 1,
        );
    }

    public static function failureDependencyValidation(): self
    {
        return new self(
            id: 'failure-dependency-validation',
            name: 'Missing Dependency Validation Failure',
            description: 'Attempting to install Audit Trails without required Authorization dependency (must fail before mutations).',
            category: 'failure',
            authFeatures: ['login', 'logout'],
            optionalModules: ['audit-trails'],
            expectedExitCode: 1,
            expectedOutputContains: ['requires the following feature(s): authorization'],
        );
    }

    public static function failurePostChiselResume(): self
    {
        return new self(
            id: 'failure-post-chisel-resume',
            name: 'Post-Chisel Failure and Recovery',
            description: 'Failure after feature pruning saves checkpoint without credentials; subsequent re-run resumes seamlessly.',
            category: 'failure',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            admin: [
                'name' => 'Recovery Admin',
                'email' => 'recovery@example.com',
                'password' => 'RecoverySecretPassword987!',
            ],
            env: ['CHISEL_TEST_FAIL_STAGE' => 'post_chisel'],
            expectedExitCode: 1,
            expectedOutputNotContains: ['RecoverySecretPassword987!'],
        );
    }

    public static function failurePostMigration(): self
    {
        return new self(
            id: 'failure-post-migration',
            name: 'Post-Migration Failure',
            description: 'Deterministic failure injection immediately following database migration.',
            category: 'failure',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            env: ['CHISEL_TEST_FAIL_STAGE' => 'post_migration'],
            expectedExitCode: 1,
        );
    }

    public static function failureCleanup(): self
    {
        return new self(
            id: 'failure-cleanup',
            name: 'Cleanup Phase Failure',
            description: 'Deterministic failure injection during final installer cleanup.',
            category: 'failure',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            env: ['CHISEL_TEST_FAIL_STAGE' => 'cleanup'],
            expectedExitCode: 1,
        );
    }

    public static function failureConflictingRetry(): self
    {
        return new self(
            id: 'failure-conflicting-retry',
            name: 'Conflicting Answers on Retry',
            description: 'Re-running installation with conflicting answers against an existing checkpoint is rejected.',
            category: 'failure',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization', 'settings'],
            expectedExitCode: 1,
            expectedOutputContains: ['Conflicting answers on installer retry'],
        );
    }

    public static function envNoNode(): self
    {
        return new self(
            id: 'env-no-node',
            name: 'No Node/Bun Mode',
            description: 'LARAVEL_INSTALLER_NO_NODE mode skips frontend asset building while preserving manifest consistency.',
            category: 'env',
            authFeatures: ['login', 'logout'],
            optionalModules: [
                'authorization',
                'user-management',
                'audit-trails',
            ],
            env: ['LARAVEL_INSTALLER_NO_NODE' => 'true'],
        );
    }

    public static function envDeferredHooks(): self
    {
        return new self(
            id: 'env-deferred-hooks',
            name: 'Deferred Hooks Mode',
            description: 'LARAVEL_INSTALLER_DEFER_HOOKS skips installer during official Laravel installer flows.',
            category: 'env',
            authFeatures: ['login', 'logout'],
            optionalModules: ['authorization'],
            env: ['LARAVEL_INSTALLER_DEFER_HOOKS' => 'true'],
            expectedExitCode: 0,
        );
    }

    public static function envMissingChisel(): self
    {
        return new self(
            id: 'env-missing-chisel',
            name: 'Missing Chisel Files',
            description: 'Graceful exit when installer script or paths manifest is absent.',
            category: 'env',
            authFeatures: ['login', 'logout'],
            optionalModules: [],
            expectedExitCode: 0,
        );
    }

    /**
     * @return array<string, self>
     */
    public static function all(): array
    {
        return [
            // Profiles
            'minimal' => self::minimal(),
            'default' => self::default(),
            'full' => self::full(),
            'auth-minimal' => self::authMinimal(),
            'no-modules' => self::noModules(),
            'dependency-heavy' => self::dependencyHeavy(),

            // Modules
            'module-authorization' => self::moduleAuthorization(),
            'module-settings' => self::moduleSettings(),
            'module-user-management' => self::moduleUserManagement(),
            'module-localization' => self::moduleLocalization(),
            'module-notifications' => self::moduleNotifications(),
            'module-audit-trails' => self::moduleAuditTrails(),

            // Auth variants
            'auth-no-registration' => self::authNoRegistration(),
            'auth-no-email-verification' => self::authNoEmailVerification(),
            'auth-no-password-reset' => self::authNoPasswordReset(),
            'auth-no-two-factor' => self::authNoTwoFactor(),
            'auth-invalid-dependency' => self::authInvalidDependency(),

            // Database
            'db-empty' => self::dbEmpty(),
            'db-migrated' => self::dbMigrated(),
            'db-early-migration-fresh' => self::dbEarlyMigrationFresh(),

            // Admin
            'admin-valid-credentials' => self::adminValidCredentials(),
            'admin-no-credentials' => self::adminNoCredentials(),
            'admin-cli-options' => self::adminCliOptions(),
            'admin-answers-payload' => self::adminAnswersPayload(),

            // Failure
            'failure-preflight' => self::failurePreflight(),
            'failure-dependency-validation' => self::failureDependencyValidation(),
            'failure-post-chisel-resume' => self::failurePostChiselResume(),
            'failure-post-migration' => self::failurePostMigration(),
            'failure-cleanup' => self::failureCleanup(),
            'failure-conflicting-retry' => self::failureConflictingRetry(),

            // Env
            'env-no-node' => self::envNoNode(),
            'env-deferred-hooks' => self::envDeferredHooks(),
            'env-missing-chisel' => self::envMissingChisel(),
        ];
    }

    /**
     * The canonical representative E2E scenarios tested with real filesystem lifecycle.
     *
     * @return array<string, self>
     */
    public static function representative(): array
    {
        return [
            'minimal' => self::minimal(),
            'default' => self::default(),
            'full' => self::full(),
            'dependency-heavy' => self::dependencyHeavy(),
            'admin-no-credentials' => self::adminNoCredentials(),
            'db-early-migration-fresh' => self::dbEarlyMigrationFresh(),
            'env-no-node' => self::envNoNode(),
            'failure-post-chisel-resume' => self::failurePostChiselResume(),
        ];
    }

    /**
     * @return array<string, self>
     */
    public static function byCategory(string $category): array
    {
        return array_filter(
            self::all(),
            fn (self $s): bool => $s->category === $category
        );
    }

    /**
     * @return array<string, array{self}>
     */
    public static function dataset(?string $category = null): array
    {
        $scenarios = $category !== null ? self::byCategory($category) : self::all();

        $dataset = [];
        foreach ($scenarios as $key => $scenario) {
            $dataset[$key] = [$scenario];
        }

        return $dataset;
    }

    /**
     * @return array{auth_features: list<string>, optional_modules: list<string>}
     */
    public function answers(): array
    {
        return [
            'auth_features' => $this->authFeatures,
            'optional_modules' => $this->optionalModules,
        ];
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->authFeatures, true)
            || in_array($feature, $this->optionalModules, true);
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

    public function isNodeSkipped(): bool
    {
        return ($this->env['LARAVEL_INSTALLER_NO_NODE'] ?? '') === 'true';
    }

    /**
     * @return list<string>
     */
    public function expectedDependencies(): array
    {
        if ($this->expectedDependencies !== null) {
            return $this->expectedDependencies;
        }

        $packages = [];
        $selectedKeys = array_merge($this->authFeatures, $this->optionalModules);

        foreach ($selectedKeys as $key) {
            try {
                $feature = FeatureRegistry::get($key);
                foreach ($feature->composerPackages as $pkg) {
                    $packages[] = $pkg;
                }

                foreach ($feature->frontendPackages as $pkg) {
                    $packages[] = $pkg;
                }
            } catch (InvalidArgumentException) {
                // Key not registered, ignore for expectation computation
            }
        }

        return array_values(array_unique($packages));
    }

    /**
     * @return list<string>
     */
    public function expectedAbsentDependencies(): array
    {
        if ($this->expectedAbsentDependencies !== null) {
            return $this->expectedAbsentDependencies;
        }

        $absent = [];
        $selectedKeys = array_merge($this->authFeatures, $this->optionalModules);

        foreach (FeatureRegistry::all() as $key => $feature) {
            if (in_array($key, $selectedKeys, true)) {
                continue;
            }

            foreach ($feature->composerPackages as $pkg) {
                $absent[] = $pkg;
            }

            foreach ($feature->frontendPackages as $pkg) {
                $absent[] = $pkg;
            }
        }

        return array_values(array_unique($absent));
    }

    /**
     * @return list<string>
     */
    public function expectedRetainedPaths(): array
    {
        return $this->expectedRetainedPaths ?? Profile::expectedPresentPathsFor($this->selectedFeatures());
    }

    /**
     * @return list<string>
     */
    public function expectedRemovedPaths(): array
    {
        return $this->expectedRemovedPaths ?? Profile::expectedAbsentPathsFor($this->selectedFeatures());
    }

    /**
     * @return list<string>
     */
    public function expectedPresentTables(): array
    {
        return $this->expectedPresentTables ?? Profile::expectedPresentTablesFor($this->selectedFeatures());
    }

    /**
     * @return list<string>
     */
    public function expectedAbsentTables(): array
    {
        return $this->expectedAbsentTables ?? Profile::expectedAbsentTablesFor($this->selectedFeatures());
    }

    /**
     * @return array<string, list<string>>
     */
    public function expectedPresentColumns(): array
    {
        return $this->expectedPresentColumns ?? Profile::expectedPresentColumnsFor($this->selectedFeatures());
    }

    /**
     * @return array<string, list<string>>
     */
    public function expectedAbsentColumns(): array
    {
        return $this->expectedAbsentColumns ?? Profile::expectedAbsentColumnsFor($this->selectedFeatures());
    }

    /**
     * @return list<string>
     */
    private function selectedFeatures(): array
    {
        return array_merge($this->authFeatures, $this->optionalModules);
    }
}
