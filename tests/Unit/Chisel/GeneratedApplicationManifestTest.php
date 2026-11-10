<?php

declare(strict_types=1);

use App\Chisel\Installer\GeneratedApplicationManifest;
use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\Preset;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/manifest_test_'.bin2hex(random_bytes(6));
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        $cleanup = function (string $dir) use (&$cleanup): void {
            $items = scandir($dir);
            if ($items === false) {
                return;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $path = $dir.'/'.$item;
                if (is_dir($path)) {
                    $cleanup($path);
                } else {
                    File::delete($path);
                }
            }

            @rmdir($dir);
        };
        $cleanup($this->tempDir);
    }
});

function makeManifestContext(
    array $answers = [],
    ?string $adminPassword = null,
    ?string $preset = null,
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
        adminName: 'Admin',
        adminEmail: 'admin@example.com',
        adminPassword: $adminPassword,
        isNonInteractive: true,
        skipNode: true,
        isMockedScript: true,
        preset: $preset,
    );
}

test('manifest constants define version 1 and starter-kit-manifest.json', function (): void {
    expect(GeneratedApplicationManifest::SCHEMA_VERSION)->toBe(1)
        ->and(GeneratedApplicationManifest::MANIFEST_FILE)->toBe('starter-kit-manifest.json');
});

test('manifest path uses default filename or env override', function (): void {
    expect(GeneratedApplicationManifest::manifestPath($this->tempDir))
        ->toBe($this->tempDir.'/starter-kit-manifest.json');

    putenv('CHISEL_MANIFEST_PATH='.$this->tempDir.'/custom-manifest.json');

    try {
        expect(GeneratedApplicationManifest::manifestPath($this->tempDir))
            ->toBe($this->tempDir.'/custom-manifest.json');
    } finally {
        putenv('CHISEL_MANIFEST_PATH');
    }
});

test('exists and read return expected results for missing and corrupted files', function (): void {
    expect(GeneratedApplicationManifest::exists($this->tempDir))->toBeFalse()
        ->and(GeneratedApplicationManifest::read($this->tempDir))->toBeNull();

    $path = $this->tempDir.'/starter-kit-manifest.json';
    file_put_contents($path, 'not-valid-json');

    expect(GeneratedApplicationManifest::exists($this->tempDir))->toBeTrue()
        ->and(GeneratedApplicationManifest::read($this->tempDir))->toBeNull();

    file_put_contents($path, '"scalar-json"');
    expect(GeneratedApplicationManifest::read($this->tempDir))->toBeNull();

    file_put_contents($path, '["array-with-int-keys"]');
    expect(GeneratedApplicationManifest::read($this->tempDir))->toBeNull();
});

test('fromContext resolves explicit features, dependency-derived features, and capabilities for full preset', function (): void {
    $context = makeManifestContext(
        answers: Preset::answersFor(Preset::FULL),
        preset: Preset::FULL,
    );

    $manifest = GeneratedApplicationManifest::fromContext($context, $this->tempDir);

    expect($manifest->schemaVersion)->toBe(1)
        ->and($manifest->preset)->toBe('full')
        ->and($manifest->features['explicit']['auth_features'])->toBe(['email-verification', 'login', 'logout', 'password-reset', 'password-update', 'registration', 'two-factor'])
        ->and($manifest->features['explicit']['optional_modules'])->toBe(['audit-trails', 'authorization', 'localization', 'notifications', 'reporting', 'settings', 'user-management'])
        ->and($manifest->features['dependency_derived'])->toContain('authorization', 'audit-trails', 'user-management', 'login')
        ->and($manifest->features['all_selected'])->toContain('authorization', 'reporting', 'two-factor')
        ->and($manifest->features['unselected'])->toBe([])
        ->and($manifest->authCapabilities)->toBe(['email-verification', 'login', 'logout', 'password-reset', 'password-update', 'registration', 'two-factor']);
});

test('fromContext auto-detects preset when answers match standard preset without explicit preset flag', function (): void {
    $context = makeManifestContext(
        answers: Preset::answersFor(Preset::STANDARD),
    );

    $manifest = GeneratedApplicationManifest::fromContext($context, $this->tempDir);

    expect($manifest->preset)->toBe('standard');
});

test('fromContext leaves preset null when answers are custom and do not match any preset', function (): void {
    $context = makeManifestContext(
        answers: [
            'auth_features' => ['login'],
            'optional_modules' => ['settings', 'authorization'],
        ],
    );

    $manifest = GeneratedApplicationManifest::fromContext($context, $this->tempDir);

    expect($manifest->preset)->toBeNull()
        ->and($manifest->features['unselected'])->toContain('reporting', 'two-factor');
});

test('fromContext resolves composer.json name and version, and honors STARTER_KIT_VERSION override', function (): void {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'name' => 'custom/my-starter',
        'version' => '1.2.3',
    ], JSON_THROW_ON_ERROR));

    $context = makeManifestContext(['auth_features' => ['login']]);
    $manifest = GeneratedApplicationManifest::fromContext($context, $this->tempDir);

    expect($manifest->starterKit['name'])->toBe('custom/my-starter')
        ->and($manifest->starterKit['version'])->toBe('1.2.3');

    putenv('STARTER_KIT_VERSION=2.0.0-beta');

    try {
        $overridden = GeneratedApplicationManifest::fromContext($context, $this->tempDir);
        expect($overridden->starterKit['version'])->toBe('2.0.0-beta');
    } finally {
        putenv('STARTER_KIT_VERSION');
    }
});

test('fromContext safely handles missing or unparseable composer.json', function (): void {
    file_put_contents($this->tempDir.'/composer.json', 'invalid-json');

    $context = makeManifestContext(['auth_features' => ['login']]);
    $manifest = GeneratedApplicationManifest::fromContext($context, $this->tempDir);

    expect($manifest->starterKit['name'])->toBe('tooinfinity/infinity-starter-kit')
        ->and($manifest->starterKit['version'])->toBeNull();
});

test('fromContext handles ai_guidelines as array and bool', function (): void {
    $contextArray = makeManifestContext([
        'auth_features' => ['login'],
        'optional_modules' => [],
        'ai_guidelines' => ['guidelines', 'tools'],
    ]);
    $manifestArray = GeneratedApplicationManifest::fromContext($contextArray, $this->tempDir);
    expect($manifestArray->features['explicit']['ai_guidelines'])->toBe(['guidelines', 'tools']);

    $contextBoolTrue = makeManifestContext([
        'auth_features' => ['login'],
        'optional_modules' => [],
        'ai_guidelines' => true,
    ]);
    $manifestBoolTrue = GeneratedApplicationManifest::fromContext($contextBoolTrue, $this->tempDir);
    expect($manifestBoolTrue->features['explicit']['ai_guidelines'])->toBe(['guidelines']);

    $contextBoolFalse = makeManifestContext([
        'auth_features' => ['login'],
        'optional_modules' => [],
        'ai_guidelines' => false,
    ]);
    $manifestBoolFalse = GeneratedApplicationManifest::fromContext($contextBoolFalse, $this->tempDir);
    expect($manifestBoolFalse->features['explicit']['ai_guidelines'])->toBe([]);
});

test('fromContext handles unknown feature keys in selected features gracefully', function (): void {
    $context = makeManifestContext([
        'auth_features' => ['login'],
        'optional_modules' => ['unknown_feature_xyz'],
    ]);

    $manifest = GeneratedApplicationManifest::fromContext($context, $this->tempDir);

    expect($manifest->features['all_selected'])->toContain('unknown_feature_xyz')
        ->and($manifest->features['dependency_derived'])->toBe([]);
});

test('resolveSourceRevision handles env overrides, git repository, and missing git directory', function (): void {
    putenv('STARTER_KIT_REVISION=abc1234');

    try {
        expect(GeneratedApplicationManifest::resolveSourceRevision($this->tempDir))->toBe('abc1234');
    } finally {
        putenv('STARTER_KIT_REVISION');
    }

    putenv('STARTER_KIT_SOURCE_REVISION=def5678');

    try {
        expect(GeneratedApplicationManifest::resolveSourceRevision($this->tempDir))->toBe('def5678');
    } finally {
        putenv('STARTER_KIT_SOURCE_REVISION');
    }

    expect(GeneratedApplicationManifest::resolveSourceRevision($this->tempDir))->toBeNull();

    if (file_exists(base_path('.git'))) {
        $rev = GeneratedApplicationManifest::resolveSourceRevision(base_path());
        expect($rev)->toBeString()->and(mb_strlen((string) $rev))->toBeGreaterThan(6);
    }

    mkdir($this->tempDir.'/.git', 0777, true);
    expect(GeneratedApplicationManifest::resolveSourceRevision($this->tempDir))->toBeNull();
});

test('resolveTimestamp supports CHISEL_MANIFEST_TIMESTAMP, SOURCE_DATE_EPOCH, and CHISEL_DETERMINISTIC_MANIFEST', function (): void {
    putenv('CHISEL_MANIFEST_TIMESTAMP=2026-01-01T12:00:00Z');

    try {
        expect(GeneratedApplicationManifest::resolveTimestamp())->toBe('2026-01-01T12:00:00Z');
    } finally {
        putenv('CHISEL_MANIFEST_TIMESTAMP');
    }

    putenv('SOURCE_DATE_EPOCH=1700000000');

    try {
        expect(GeneratedApplicationManifest::resolveTimestamp())->toBe(gmdate('Y-m-d\TH:i:s\Z', 1700000000));
    } finally {
        putenv('SOURCE_DATE_EPOCH');
    }

    putenv('CHISEL_DETERMINISTIC_MANIFEST=true');

    try {
        expect(GeneratedApplicationManifest::resolveTimestamp())->toBe('1970-01-01T00:00:00Z');
    } finally {
        putenv('CHISEL_DETERMINISTIC_MANIFEST');
    }

    expect(GeneratedApplicationManifest::resolveTimestamp())->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
});

test('assertNoSensitiveData rejects sensitive keywords and connection strings', function (): void {
    expect(fn () => GeneratedApplicationManifest::assertNoSensitiveData(['admin_password' => 'secret']))
        ->toThrow(RuntimeException::class, 'Sensitive field [admin_password] cannot be serialized');

    expect(fn () => GeneratedApplicationManifest::assertNoSensitiveData(['secret_token' => 'val']))
        ->toThrow(RuntimeException::class, 'Sensitive field [secret_token] cannot be serialized');

    expect(fn () => GeneratedApplicationManifest::assertNoSensitiveData(['nested' => ['api_key' => '123']]))
        ->toThrow(RuntimeException::class, 'Sensitive field [api_key] cannot be serialized');

    expect(fn () => GeneratedApplicationManifest::assertNoSensitiveData(['nested' => ['bearer_token' => '123']]))
        ->toThrow(RuntimeException::class, 'Sensitive field [bearer_token] cannot be serialized');

    expect(fn () => GeneratedApplicationManifest::assertNoSensitiveData(['database' => 'postgres://user:super_secret@localhost:5432/app']))
        ->toThrow(RuntimeException::class, 'Connection string with credentials cannot be serialized');

    expect(fn () => GeneratedApplicationManifest::assertNoSensitiveData([
        'schema_version' => 1,
        'features' => ['login', 'registration'],
    ]))->not->toThrow(Throwable::class);
});

test('sensitive installer fields cannot be serialized into manifest', function (): void {
    $context = makeManifestContext(
        answers: [
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
            'admin' => [
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => 'super_secret_password_123',
            ],
            'admin_password' => 'super_secret_password_123',
            'api_token' => 'top_secret_token',
        ],
        adminPassword: 'super_secret_password_123',
    );

    $manifest = GeneratedApplicationManifest::fromContext($context, $this->tempDir);
    $data = $manifest->toArray();
    $json = $manifest->toJson();

    expect($json)->not->toContain('super_secret_password_123')
        ->and($json)->not->toContain('top_secret_token')
        ->and($data)->not->toHaveKey('admin')
        ->and($data)->not->toHaveKey('admin_password')
        ->and($data)->not->toHaveKey('password');
});

test('write creates atomic manifest file and it can be read without installer classes', function (): void {
    $context = makeManifestContext(
        answers: [
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['settings', 'authorization'],
        ],
    );

    $writtenPath = GeneratedApplicationManifest::write($this->tempDir, $context);

    expect(file_exists($writtenPath))->toBeTrue();

    $rawContents = (string) file_get_contents($writtenPath);
    $decoded = json_decode($rawContents, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray()
        ->and($decoded['schema_version'])->toBe(1)
        ->and($decoded['starter_kit']['name'])->toBe('tooinfinity/infinity-starter-kit')
        ->and($decoded['features']['explicit']['auth_features'])->toBe(['login', 'registration'])
        ->and($decoded['features']['explicit']['optional_modules'])->toBe(['authorization', 'settings'])
        ->and($decoded['features']['dependency_derived'])->toBe(['authorization', 'login'])
        ->and($decoded['auth_capabilities'])->toBe(['login', 'registration'])
        ->and($decoded)->toHaveKey('generated_at');

    $readManifest = GeneratedApplicationManifest::read($this->tempDir);
    expect($readManifest)->toBe($decoded);
});

test('write throws RuntimeException if target directory is invalid', function (): void {
    $context = makeManifestContext(['auth_features' => ['login']]);
    $invalidDir = '/non_existent_directory_'.bin2hex(random_bytes(6));

    expect(fn (): string => GeneratedApplicationManifest::write($invalidDir, $context))
        ->toThrow(RuntimeException::class, 'Failed to write temporary starter kit manifest');
});

test('write throws RuntimeException if atomic rename fails', function (): void {
    $context = makeManifestContext(['auth_features' => ['login']]);
    mkdir($this->tempDir.'/starter-kit-manifest.json', 0777, true);

    expect(fn (): string => GeneratedApplicationManifest::write($this->tempDir, $context))
        ->toThrow(RuntimeException::class, 'Failed to atomically rename starter kit manifest');
});

test('example generates a valid canonical manifest for documentation and tooling', function (): void {
    $example = GeneratedApplicationManifest::example();

    expect($example->schemaVersion)->toBe(GeneratedApplicationManifest::SCHEMA_VERSION)
        ->and($example->preset)->toBe(Preset::STANDARD)
        ->and($example->starterKit['name'])->toBe('tooinfinity/infinity-starter-kit')
        ->and($example->starterKit['version'])->toBe('1.0.0')
        ->and($example->starterKit['source_revision'])->toBe('1c253c2000000000000000000000000000000000')
        ->and($example->features['explicit']['auth_features'])->toBe(['email-verification', 'login', 'logout', 'password-reset', 'password-update', 'registration'])
        ->and($example->features['explicit']['optional_modules'])->toBe(['authorization', 'notifications', 'settings', 'user-management'])
        ->and($example->features['unselected'])->toBe(['audit-trails', 'localization', 'reporting', 'two-factor'])
        ->and($example->generatedAt)->toBe('2026-11-02T22:00:00Z');

    $array = $example->toArray();
    expect($array['schema_version'])->toBe(1)
        ->and($array)->toHaveKeys(['schema_version', 'starter_kit', 'preset', 'features', 'auth_capabilities', 'generated_at']);

    $json = $example->toJson();
    expect(json_validate($json))->toBeTrue();
});
