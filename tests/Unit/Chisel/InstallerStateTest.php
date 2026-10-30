<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\InstallerState;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/installer_state_test_'.bin2hex(random_bytes(6));
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        if (file_exists($this->tempDir.'/'.InstallerState::STATE_FILE)) {
            @unlink($this->tempDir.'/'.InstallerState::STATE_FILE);
        }

        @rmdir($this->tempDir);
    }
});

function makeTestContext(
    array $answers = [],
    ?string $adminName = null,
    ?string $adminEmail = null,
    ?string $adminPassword = null,
): InstallerContext {
    $rawAuth = (array) ($answers['auth_features'] ?? []);
    $auth = array_values(array_filter($rawAuth, is_string(...)));
    $rawModules = (array) ($answers['optional_modules'] ?? []);
    $modules = array_values(array_filter($rawModules, is_string(...)));

    return new InstallerContext(
        providedAnswers: $answers,
        answers: $answers,
        paths: [],
        selectedModules: $modules,
        selectedAuthFeatures: $auth,
        hasAuthorization: in_array('authorization', $modules, true),
        adminName: $adminName,
        adminEmail: $adminEmail,
        adminPassword: $adminPassword,
        isNonInteractive: true,
        skipNode: true,
        isMockedScript: true,
    );
}

test('initialize creates state file with sanitized answers and no secrets', function (): void {
    $context = makeTestContext(
        answers: [
            'auth_features' => ['registration', 'login'],
            'optional_modules' => ['authorization'],
            'admin' => ['password' => 'super_secret_123'],
            'admin_password' => 'super_secret_123',
        ],
        adminName: 'Admin Guy',
        adminEmail: 'admin@example.com',
        adminPassword: 'super_secret_123',
    );

    $state = InstallerState::initialize($this->tempDir, $context);

    expect(InstallerState::exists($this->tempDir))->toBeTrue()
        ->and($state->status)->toBe('in_progress')
        ->and($state->currentPhase)->toBe('preflight')
        ->and($state->completedPhases)->toBe([])
        ->and($state->answers)->toBe([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ])
        ->and($state->metadata)->toBe([
            'admin_name' => 'Admin Guy',
            'admin_email' => 'admin@example.com',
        ]);

    $rawJson = (string) file_get_contents($this->tempDir.'/'.InstallerState::STATE_FILE);
    expect($rawJson)->not->toContain('super_secret_123')
        ->and($rawJson)->not->toContain('password');
});

test('load retrieves saved state accurately from disk', function (): void {
    $context = makeTestContext(
        answers: [
            'auth_features' => ['login'],
            'optional_modules' => ['settings'],
        ],
        adminName: 'Jane Doe',
        adminEmail: 'jane@example.com',
    );

    $saved = InstallerState::initialize($this->tempDir, $context);
    $saved->markPhaseComplete($this->tempDir, 'preflight');
    $saved->markPhaseComplete($this->tempDir, 'prune');

    $loaded = InstallerState::load($this->tempDir);

    expect($loaded)->not->toBeNull()
        ->and($loaded?->hasCompleted('preflight'))->toBeTrue()
        ->and($loaded?->hasCompleted('prune'))->toBeTrue()
        ->and($loaded?->hasCompleted('migrate'))->toBeFalse()
        ->and($loaded?->answers['auth_features'])->toBe(['login'])
        ->and($loaded?->metadata['admin_email'])->toBe('jane@example.com');
});

test('load returns null when state file does not exist or is corrupted', function (): void {
    expect(InstallerState::load($this->tempDir))->toBeNull();

    file_put_contents($this->tempDir.'/'.InstallerState::STATE_FILE, 'not json');
    expect(InstallerState::load($this->tempDir))->toBeNull();
});

test('recordFailure persists failure phase and redacts secrets in error message', function (): void {
    $context = makeTestContext(
        answers: ['auth_features' => ['login'], 'optional_modules' => []],
    );

    $state = InstallerState::initialize($this->tempDir, $context);
    $state->recordFailure($this->tempDir, 'migrate', 'Migration failed with --password=very_secret_pass');

    $loaded = InstallerState::load($this->tempDir);

    expect($loaded?->status)->toBe('failed')
        ->and($loaded?->failedPhase)->toBe('migrate')
        ->and($loaded?->lastError)->toContain('--password=[REDACTED]')
        ->and($loaded?->lastError)->not->toContain('very_secret_pass');
});

test('answersConflict detects mismatch between saved state and current context', function (): void {
    $contextOriginal = makeTestContext(
        answers: [
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ],
    );

    $state = InstallerState::initialize($this->tempDir, $contextOriginal);

    // Matching answers in different order
    $contextMatching = makeTestContext(
        answers: [
            'auth_features' => ['registration', 'login'],
            'optional_modules' => ['authorization'],
        ],
    );
    expect($state->answersConflict($contextMatching))->toBeFalse();

    // Conflicting auth features
    $contextDifferentAuth = makeTestContext(
        answers: [
            'auth_features' => ['login'],
            'optional_modules' => ['authorization'],
        ],
    );
    expect($state->answersConflict($contextDifferentAuth))->toBeTrue();

    // Conflicting optional modules
    $contextDifferentModules = makeTestContext(
        answers: [
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization', 'settings'],
        ],
    );
    expect($state->answersConflict($contextDifferentModules))->toBeTrue();
});

test('formatConflictMessage provides clear explanation of saved vs requested answers', function (): void {
    $contextOriginal = makeTestContext(
        answers: [
            'auth_features' => ['login'],
            'optional_modules' => [],
        ],
    );

    $state = InstallerState::initialize($this->tempDir, $contextOriginal);

    $contextRetry = makeTestContext(
        answers: [
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ],
    );

    $message = $state->formatConflictMessage($contextRetry);

    expect($message)->toContain('Conflicting answers on installer retry.')
        ->and($message)->toContain('login')
        ->and($message)->toContain('registration')
        ->and($message)->toContain('authorization')
        ->and($message)->toContain('php artisan install:features')
        ->and($message)->toContain('composer create-project');
});

test('delete removes state file from disk', function (): void {
    $context = makeTestContext(answers: ['auth_features' => ['login'], 'optional_modules' => []]);
    $state = InstallerState::initialize($this->tempDir, $context);

    expect(file_exists($this->tempDir.'/'.InstallerState::STATE_FILE))->toBeTrue();

    $state->delete($this->tempDir);

    expect(file_exists($this->tempDir.'/'.InstallerState::STATE_FILE))->toBeFalse();
    // Deleting when file does not exist returns true
    expect($state->delete($this->tempDir))->toBeTrue();
});

test('load returns null when state file is not an array', function (): void {
    file_put_contents($this->tempDir.'/'.InstallerState::STATE_FILE, '"not an array"');
    expect(InstallerState::load($this->tempDir))->toBeNull();
});

test('sanitizeMetadata filters nested secrets and keeps scalar values', function (): void {
    $metadata = [
        'safe_key' => 'safe_value',
        'auth_token' => 'secret_token_123',
        'nested' => [
            'api_secret' => 'super_secret',
            'admin_password' => 'secret_pass',
            'app_name' => 'My App',
            'count' => 42,
            'is_valid' => true,
            'empty_val' => null,
        ],
    ];

    $sanitized = InstallerState::sanitizeMetadata($metadata);

    expect($sanitized)->toHaveKey('safe_key')
        ->and($sanitized)->not->toHaveKey('auth_token')
        ->and($sanitized['nested'])->toHaveKey('app_name')
        ->and($sanitized['nested'])->toHaveKey('count')
        ->and($sanitized['nested'])->toHaveKey('is_valid')
        ->and($sanitized['nested'])->toHaveKey('empty_val')
        ->and($sanitized['nested'])->not->toHaveKey('api_secret')
        ->and($sanitized['nested'])->not->toHaveKey('admin_password');
});
