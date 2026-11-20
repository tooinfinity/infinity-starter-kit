<?php

declare(strict_types=1);

use App\Chisel\FeatureRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

test('feature registry contains all six core features evaluated in package strategy audit', function (): void {
    $features = FeatureRegistry::all();

    expect($features)->toHaveKeys([
        'authorization',
        'settings',
        'user-management',
        'notifications',
        'audit-trails',
        'reporting',
    ]);
});

test('inter-feature dependency map satisfies package strategy architecture invariants', function (): void {
    $features = FeatureRegistry::all();

    expect($features['settings']->dependencies)->toContain('authorization');
    expect($features['user-management']->dependencies)->toContain('authorization');
    expect($features['audit-trails']->dependencies)->toContain('authorization');

    expect($features['reporting']->dependencies)->toEqualCanonicalizing([
        'audit-trails',
        'user-management',
        'authorization',
    ]);
});

test('all exclusive and marker files for audited features exist on disk', function (): void {
    $auditedKeys = [
        'authorization',
        'settings',
        'user-management',
        'notifications',
        'audit-trails',
        'reporting',
    ];

    foreach ($auditedKeys as $key) {
        $feature = FeatureRegistry::get($key);

        foreach ($feature->exclusiveFiles as $file) {
            expect(file_exists(base_path($file)))->toBeTrue(
                sprintf('Exclusive file [%s] for feature [%s] must exist.', $file, $key)
            );
        }

        foreach ($feature->markerFiles as $file) {
            expect(file_exists(base_path($file)))->toBeTrue(
                sprintf('Marker file [%s] for feature [%s] must exist.', $file, $key)
            );
        }
    }
});

test('audited domain models strictly adhere to the HasUuids contract when installed', function (): void {
    $models = [
        User::class,
        'App\\Models\\'.'Setting',
        'App\\Models\\'.'AuditTrail',
        'App\\Models\\'.'Activity',
        'App\\Models\\'.'NotificationPreference',
    ];

    foreach ($models as $modelClass) {
        if (! class_exists($modelClass)) {
            continue;
        }

        $traits = class_uses_recursive($modelClass);
        expect(in_array(HasUuids::class, $traits, true))->toBeTrue(
            sprintf('Model [%s] must use HasUuids trait for uniform UUID primary key support.', $modelClass)
        );
    }
});

test('audit trail model declares null updated_at and immutability handlers when installed', function (): void {
    $auditTrailClass = 'App\\Models\\'.'AuditTrail';
    if (! class_exists($auditTrailClass)) {
        return;
    }

    expect($auditTrailClass::UPDATED_AT)->toBeNull();

    $dispatcher = $auditTrailClass::getEventDispatcher();
    expect($dispatcher)->not->toBeNull();

    $auditTrail = new $auditTrailClass;

    expect(fn () => $dispatcher->until('eloquent.updating: '.$auditTrailClass, $auditTrail))
        ->toThrow(RuntimeException::class, 'Audit trail records are immutable and cannot be updated.');

    expect(fn () => $dispatcher->until('eloquent.deleting: '.$auditTrailClass, $auditTrail))
        ->toThrow(RuntimeException::class, 'Audit trail records cannot be individually deleted. Use the retention prune command instead.');
});

test('record audit trail redacts sensitive fields and credentials by design when installed', function (): void {
    $recordAuditTrailClass = 'App\\Actions\\AuditTrails\\'.'RecordAuditTrail';
    if (! class_exists($recordAuditTrailClass)) {
        return;
    }

    $recorder = new $recordAuditTrailClass;
    $reflection = new ReflectionClass($recorder);
    $redactMethod = $reflection->getMethod('redact');

    /** @var array<string, mixed> $redacted */
    $redacted = $redactMethod->invoke($recorder, [
        'name' => 'Acme Admin',
        'password' => 'secret-password-123',
        'password_confirmation' => 'secret-password-123',
        'remember_token' => 'random-remember-token',
        'two_factor_secret' => '2fa-secret-code',
        'api_key' => 'live_api_key_xyz',
        'nested' => [
            'oauth_token' => 'bearer-token-val',
            'public_field' => 'visible-data',
        ],
    ]);

    expect($redacted['name'])->toBe('Acme Admin');
    expect($redacted['password'])->toBe('[REDACTED]');
    expect($redacted['password_confirmation'])->toBe('[REDACTED]');
    expect($redacted['remember_token'])->toBe('[REDACTED]');
    expect($redacted['two_factor_secret'])->toBe('[REDACTED]');
    expect($redacted['api_key'])->toBe('[REDACTED]');
    expect($redacted['nested']['oauth_token'])->toBe('[REDACTED]');
    expect($redacted['nested']['public_field'])->toBe('visible-data');
});

test('time bucket sql supports all required relational database dialects when installed', function (): void {
    $timeBucketClass = 'App\\Queries\\Reporting\\'.'TimeBucketSql';
    if (! class_exists($timeBucketClass)) {
        return;
    }

    $drivers = ['sqlite', 'pgsql', 'sqlsrv', 'mysql'];

    foreach ($drivers as $driver) {
        $sql = $timeBucketClass::forDriver($driver, 'created_at');
        expect($sql)->toBeString()
            ->and($sql)->not->toBeEmpty();
    }
});

test('package strategy baseline audit documentation exists and contains all required sections', function (): void {
    $docPath = base_path('docs/package-strategy-audit.md');

    expect(file_exists($docPath))->toBeTrue('docs/package-strategy-audit.md must exist.');

    $content = (string) file_get_contents($docPath);

    expect($content)->toContain('# Package Strategy & Baseline Compatibility Audit (Phase 0)')
        ->and($content)->toContain('Issue #168')
        ->and($content)->toContain('spatie/laravel-permission')
        ->and($content)->toContain('spatie/laravel-activitylog')
        ->and($content)->toContain('spatie/laravel-settings')
        ->and($content)->toContain('maatwebsite/excel')
        ->and($content)->toContain('POS')
        ->and($content)->toContain('FTTH ERP')
        ->and($content)->toContain('Clinic Management')
        ->and($content)->toContain('Queue Management');
});
