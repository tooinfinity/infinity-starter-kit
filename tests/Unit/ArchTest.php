<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel()/* @chisel-audit-trails */
    ->ignoring([
        'App\Listeners\AuditTrails\AuditAuthenticationEvents',
    ])/* @end-chisel-audit-trails */;
arch()->preset()->security()->ignoring([
    'assert',
]);

// 1. Controllers
arch('controllers are final and readonly')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('controllers are not used directly in application code')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

// 2. Actions
arch('actions are final and readonly')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('actions expose a handle method')
    ->expect('App\Actions')
    ->classes()
    ->toHaveMethod('handle');

arch('actions expose handle as their only public operation')
    ->expect('App\Actions')
    ->classes()
    ->not->toHavePublicMethodsBesides([
        '__construct',
        'handle',
    ])/* @chisel-settings */
    ->ignoring([
        'App\Actions\UpdateSettings', // Intentional exception: provides set(SettingKey, mixed) for single setting updates
    ])/* @end-chisel-settings */;

arch('actions do not use forbidden non-crud prefixes')
    ->expect('App\Actions')
    ->not->toHavePrefix('Get')
    ->not->toHavePrefix('Fetch')
    ->not->toHavePrefix('Save')
    ->not->toHavePrefix('Sync')
    ->not->toHavePrefix('Store')
    ->not->toHavePrefix('Process')
    ->not->toHavePrefix('Export');

test('actions adhere to CRUD naming conventions with documented exceptions', function (): void {
    $allowedPrefixes = ['Create', 'Update', 'Delete'];
    $intentionalExceptions = [
        /* @chisel-audit-trails */
        'App\Actions\AuditTrails\RecordAuditTrail',
        /* @end-chisel-audit-trails */
        /* @chisel-notifications */
        'App\Actions\Notifications\MarkAllNotificationsAsRead',
        'App\Actions\Notifications\MarkNotificationAsRead',
        /* @end-chisel-notifications */
    ];

    $actionFiles = new Finder()
        ->files()
        ->in(dirname(__DIR__, 2).'/app/Actions')
        ->name('*.php');

    foreach ($actionFiles as $file) {
        $className = 'App\\Actions\\'.str_replace(
            ['/', '.php'],
            ['\\', ''],
            $file->getRelativePathname()
        );

        if (in_array($className, $intentionalExceptions, true)) {
            continue;
        }

        $shortName = class_basename($className);
        $hasAllowedPrefix = array_any(
            $allowedPrefixes,
            fn (string $prefix): bool => str_starts_with($shortName, $prefix)
        );

        expect($hasAllowedPrefix)
            ->toBeTrue("Action [{$className}] must start with Create, Update, or Delete unless documented as an exception.");
    }
});

arch('actions do not depend on http responses')
    ->expect('App\Actions')
    ->not->toUse([
        'Illuminate\Http\RedirectResponse',
        'Illuminate\Http\JsonResponse',
        'Illuminate\Http\Response',
        'Symfony\Component\HttpFoundation\Response',
    ]);

// 3. Enums
arch('enums are strictly defined within app enums')
    ->expect('App\Enums')
    ->toBeEnums()
    ->toBeStringBackedEnums();

arch('no enums exist outside app enums')
    ->expect('App')
    ->not->toBeEnums()
    ->ignoring('App\Enums');

// 4. Installer / Chisel Boundary
$installerPackages = [
    implode('\\', ['App', 'Ch'.'isel']),
    implode('\\', ['Laravel', 'Ch'.'isel']),
];

arch('runtime application code does not depend on chisel or installer internals')
    ->expect([
        'App\Actions',
        'App\Http',
        'App\Models',
        'App\Queries',
        'App\Enums',
        'App\Rules',
        'App\Services',
        /* @chisel-notifications */
        'App\Notifications',
        /* @end-chisel-notifications */
        'App\Data',
        'App\Providers',
        /* @chisel-audit-trails */
        'App\Listeners',
        /* @end-chisel-audit-trails */
    ])
    ->not->toUse($installerPackages);

$commandFiles = glob(dirname(__DIR__, 2).'/app/Console/Commands/*.php') ?: [];
$installerCommandPrefix = 'App\Console\Commands\Install';
$installerDocsCommand = 'App\Console\Commands\GenerateFeatureDocsCommand';
$runtimeCommands = [];

foreach ($commandFiles as $file) {
    $className = 'App\Console\Commands\\'.basename($file, '.php');
    if ($className !== $installerDocsCommand && ! str_starts_with($className, $installerCommandPrefix)) {
        $runtimeCommands[] = $className;
    }
}

arch('runtime console commands do not depend on chisel or installer internals')
    ->expect($runtimeCommands !== [] ? $runtimeCommands : 'App\Console\Commands')
    ->not->toUse($installerPackages);

// 5. Cross-Feature Modular Boundaries
/* @chisel-audit-trails */
arch('audit-trails does not depend on reporting or user management')
    ->expect([
        'App\Actions\AuditTrails',
        'App\Http\Controllers\AuditTrails',
        'App\Http\Requests\AuditTrails',
        'App\Listeners\AuditTrails',
        'App\Queries\AuditTrails',
        'App\Models\AuditTrail',
        'App\Enums\AuditEvent',
        'App\Data\AuditTrails',
    ])
    ->not->toUse([
        'App\Http\Controllers\Reporting',
        'App\Queries\Reporting',
        'App\Data\Reporting',
        'App\Enums\ReportType',
        'App\Enums\ReportCategory',
        'App\Actions\Users',
        'App\Http\Controllers\Users',
        'App\Queries\Users',
        'App\Data\Users',
    ]);
/* @end-chisel-audit-trails */

/* @chisel-settings */
arch('settings does not depend on reporting or user management')
    ->expect([
        'App\Actions\UpdateSettings',
        'App\Http\Controllers\SettingController',
        'App\Http\Requests\EditSettingsRequest',
        'App\Http\Requests\UpdateSettingsRequest',
        'App\Http\Middleware\HandleSettings',
        'App\Models\Setting',
        'App\Queries\SettingQuery',
        'App\Enums\SettingKey',
        'App\Enums\SettingGroup',
    ])
    ->not->toUse([
        'App\Http\Controllers\Reporting',
        'App\Queries\Reporting',
        'App\Data\Reporting',
        'App\Enums\ReportType',
        'App\Enums\ReportCategory',
        'App\Actions\Users',
        'App\Http\Controllers\Users',
        'App\Queries\Users',
        'App\Data\Users',
        'App\Actions\Notifications',
        'App\Notifications',
    ]);
/* @end-chisel-settings */

/* @chisel-notifications */
arch('notifications does not depend on reporting, audit trails, or settings')
    ->expect([
        'App\Actions\Notifications',
        'App\Http\Controllers\NotificationController',
        'App\Http\Controllers\NotificationPreferenceController',
        'App\Http\Controllers\MarkAllNotificationsAsReadController',
        'App\Http\Requests\Notifications',
        'App\Models\NotificationPreference',
        'App\Notifications',
        'App\Enums\NotificationType',
    ])
    ->not->toUse([
        'App\Http\Controllers\Reporting',
        'App\Queries\Reporting',
        'App\Data\Reporting',
        'App\Enums\ReportType',
        'App\Enums\ReportCategory',
        'App\Actions\AuditTrails',
        'App\Models\AuditTrail',
        'App\Enums\AuditEvent',
        'App\Actions\Users',
        'App\Http\Controllers\Users',
        'App\Queries\Users',
        'App\Data\Users',
        'App\Actions\UpdateSettings',
        'App\Models\Setting',
        'App\Enums\SettingKey',
        'App\Enums\SettingGroup',
    ]);
/* @end-chisel-notifications */

/* @chisel-localization */
arch('localization does not depend on other optional feature modules')
    ->expect([
        'App\Actions\UpdateUserLocale',
        'App\Http\Controllers\LocaleController',
        'App\Http\Middleware\HandleLocale',
        'App\Http\Requests\UpdateLocaleRequest',
        'App\Services\Locales',
        'App\Enums\Locale',
    ])
    ->not->toUse([
        'App\Http\Controllers\Reporting',
        'App\Queries\Reporting',
        'App\Data\Reporting',
        'App\Enums\ReportType',
        'App\Enums\ReportCategory',
        'App\Actions\AuditTrails',
        'App\Models\AuditTrail',
        'App\Enums\AuditEvent',
        'App\Actions\Users',
        'App\Http\Controllers\Users',
        'App\Queries\Users',
        'App\Data\Users',
        'App\Actions\Notifications',
        'App\Notifications',
        'App\Enums\NotificationType',
        'App\Actions\UpdateSettings',
        'App\Models\Setting',
        'App\Enums\SettingKey',
        'App\Enums\SettingGroup',
    ]);
/* @end-chisel-localization */

/* @chisel-user-management */
arch('user management does not depend on reporting')
    ->expect([
        'App\Actions\Users',
        'App\Http\Controllers\Users',
        'App\Http\Requests\Users',
        'App\Queries\Users',
        'App\Data\Users',
        'App\Rules\CanAssignRoles',
        'App\Rules\NotLastSuperAdmin',
        'App\Enums\SuperAdminRestrictionOperation',
    ])
    ->not->toUse([
        'App\Http\Controllers\Reporting',
        'App\Queries\Reporting',
        'App\Data\Reporting',
        'App\Enums\ReportType',
        'App\Enums\ReportCategory',
    ]);
/* @end-chisel-user-management */

/* @chisel-reporting */
arch('reporting does not depend on settings, notifications, or localization')
    ->expect([
        'App\Data\Reporting',
        'App\Http\Controllers\Reporting',
        'App\Http\Requests\Reporting',
        'App\Queries\Reporting',
        'App\Enums\ReportType',
        'App\Enums\ReportCategory',
    ])
    ->not->toUse([
        'App\Actions\UpdateSettings',
        'App\Models\Setting',
        'App\Actions\Notifications',
        'App\Notifications',
        'App\Actions\UpdateUserLocale',
        'App\Services\Locales',
    ]);
/* @end-chisel-reporting */

// 6. Test Suite Organization Boundaries
test('http controller and middleware tests belong strictly in tests/Feature', function (): void {
    $unitTests = new Finder()
        ->files()
        ->in(__DIR__)
        ->name(['*ControllerTest.php', '*MiddlewareTest.php']);

    expect(iterator_to_array($unitTests))->toBeEmpty();
});
