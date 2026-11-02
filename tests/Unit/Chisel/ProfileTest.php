<?php

declare(strict_types=1);

namespace Tests\Unit\Chisel;

use App\Chisel\FeatureRegistry;
use App\Chisel\Installer\DependencyValidator;
use App\Chisel\Installer\Preset;
use App\Chisel\Installer\Profile;
use InvalidArgumentException;
use Throwable;

test('profile names returns all defined profile values', function (): void {
    expect(Profile::names())->toBe(['minimal', 'default', 'standard', 'full']);
});

test('profile all returns profile instances for all defined names', function (): void {
    $all = Profile::all();

    expect($all)->toHaveCount(4)
        ->and(array_map(fn (Profile $p): string => $p->name, $all))->toBe(Profile::names());
});

test('profile representative returns minimal, default, and full profiles', function (): void {
    $representative = Profile::representative();

    expect($representative)->toHaveCount(3)
        ->and(array_map(fn (Profile $p): string => $p->name, $representative))->toBe([
            Profile::MINIMAL,
            Profile::DEFAULT,
            Profile::FULL,
        ]);
});

test('profile fromName resolves valid profile names case-insensitively with trimming', function (string $input, string $expectedName): void {
    $profile = Profile::fromName($input);

    expect($profile->name)->toBe($expectedName);
})->with([
    'minimal exact' => ['minimal', Profile::MINIMAL],
    'minimal uppercase' => ['MINIMAL', Profile::MINIMAL],
    'minimal padded' => ['  minimal  ', Profile::MINIMAL],
    'default exact' => ['default', Profile::DEFAULT],
    'default mixed' => ['Default', Profile::DEFAULT],
    'standard exact' => ['standard', Profile::STANDARD],
    'standard mixed' => ['Standard', Profile::STANDARD],
    'full exact' => ['full', Profile::FULL],
    'full uppercase' => ['FULL', Profile::FULL],
]);

test('profile fromName throws InvalidArgumentException for unknown profile names', function (string $invalid): void {
    expect(fn (): Profile => Profile::fromName($invalid))
        ->toThrow(InvalidArgumentException::class, "Unknown profile [{$invalid}]. Available profiles: minimal, default, standard, full.");
})->with([
    'empty' => [''],
    'unknown' => ['unknown'],
    'custom' => ['custom-profile'],
]);

test('profile answersFor throws InvalidArgumentException for unknown profile name', function (): void {
    expect(fn (): array => Profile::answersFor('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unknown profile [unknown]. Available profiles: minimal, default, standard, full.');
});

test('every profile passes dependency validation', function (Profile $profile): void {
    expect(fn () => DependencyValidator::validate($profile->answers()))
        ->not->toThrow(Throwable::class);
})->with(Profile::representative());

test('all features in profiles exist in feature registry', function (Profile $profile): void {
    $validKeys = array_keys(FeatureRegistry::all());

    $answers = $profile->answers();
    foreach ($answers['auth_features'] as $feature) {
        expect($validKeys)->toContain($feature);
    }

    foreach ($answers['optional_modules'] as $module) {
        expect($validKeys)->toContain($module);
    }
})->with(Profile::representative());

test('minimal profile provides core auth with no optional modules and maps to minimal preset', function (): void {
    $profile = Profile::fromName(Profile::MINIMAL);

    expect($profile->answers())->toBe(Profile::answersFor(Profile::MINIMAL))
        ->and($profile->presetName())->toBe(Preset::MINIMAL)
        ->and($profile->hasAuthorization())->toBeFalse()
        ->and($profile->hasTwoFactor())->toBeFalse()
        ->and($profile->hasAuditTrails())->toBeFalse()
        ->and($profile->hasNotifications())->toBeFalse()
        ->and($profile->hasReporting())->toBeFalse()
        ->and($profile->hasLocalization())->toBeFalse()
        ->and($profile->hasUserManagement())->toBeFalse()
        ->and($profile->hasSettings())->toBeFalse()
        ->and($profile->expectedPresentTables())->toBe(['users'])
        ->and($profile->expectedAbsentTables())->toContain('audit_trails', 'roles', 'permissions', 'settings', 'notifications')
        ->and($profile->expectedPresentPaths())->toContain('app/Models/User.php', 'routes/web.php')
        ->and($profile->expectedAbsentPaths())->toContain('config/permission.php', 'app/Models/AuditTrail.php')
        ->and($profile->expectedForbiddenTokens())->toContain('AuditTrail', 'NotificationPreference', 'ReportIndexController', 'HandleLocale')
        ->and($profile->expectedAbsentColumns()['users'])->toContain('two_factor_secret', 'locale');
});

test('default profile matches standard preset and provides default application features', function (): void {
    $defaultProfile = Profile::fromName(Profile::DEFAULT);
    $standardProfile = Profile::fromName(Profile::STANDARD);

    expect($defaultProfile->answers())->toBe($standardProfile->answers())
        ->and($defaultProfile->presetName())->toBe(Preset::STANDARD)
        ->and($standardProfile->presetName())->toBe(Preset::STANDARD)
        ->and($defaultProfile->hasAuthorization())->toBeTrue()
        ->and($defaultProfile->hasSettings())->toBeTrue()
        ->and($defaultProfile->hasUserManagement())->toBeTrue()
        ->and($defaultProfile->hasNotifications())->toBeTrue()
        ->and($defaultProfile->hasTwoFactor())->toBeFalse()
        ->and($defaultProfile->hasAuditTrails())->toBeFalse()
        ->and($defaultProfile->hasReporting())->toBeFalse()
        ->and($defaultProfile->hasLocalization())->toBeFalse()
        ->and($defaultProfile->expectedPresentTables())->toContain('users', 'roles', 'permissions', 'settings', 'notifications')
        ->and($defaultProfile->expectedPresentPaths())->toContain('config/permission.php', 'app/Models/Setting.php', 'config/data.php')
        ->and($defaultProfile->expectedAbsentPaths())->toContain('app/Models/AuditTrail.php')
        ->and($defaultProfile->expectedAbsentPaths())->not->toContain('config/data.php')
        ->and($defaultProfile->expectedAbsentColumns()['users'])->toContain('two_factor_secret', 'locale');
});

test('full profile provides all available features and maps to full preset', function (): void {
    $profile = Profile::fromName(Profile::FULL);

    expect($profile->answers())->toBe(Profile::answersFor(Profile::FULL))
        ->and($profile->presetName())->toBe(Preset::FULL)
        ->and($profile->hasAuthorization())->toBeTrue()
        ->and($profile->hasSettings())->toBeTrue()
        ->and($profile->hasUserManagement())->toBeTrue()
        ->and($profile->hasNotifications())->toBeTrue()
        ->and($profile->hasTwoFactor())->toBeTrue()
        ->and($profile->hasAuditTrails())->toBeTrue()
        ->and($profile->hasReporting())->toBeTrue()
        ->and($profile->hasLocalization())->toBeTrue()
        ->and($profile->expectedPresentTables())->toContain('users', 'roles', 'permissions', 'settings', 'notifications', 'audit_trails')
        ->and($profile->expectedAbsentTables())->toBe([])
        ->and($profile->expectedPresentPaths())->toContain('config/permission.php', 'app/Models/AuditTrail.php', 'config/data.php')
        ->and($profile->expectedAbsentPaths())->toBe([])
        ->and($profile->expectedForbiddenTokens())->toBe([])
        ->and($profile->expectedPresentColumns()['users'])->toContain('two_factor_secret', 'locale')
        ->and($profile->expectedAbsentColumns()['users'])->toBe([]);
});
