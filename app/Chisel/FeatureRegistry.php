<?php

declare(strict_types=1);

namespace App\Chisel;

use App\Chisel\Features\AuthFeatures;
use App\Chisel\Features\OptionalModules;
use App\Chisel\Installer\Cleanup;
use InvalidArgumentException;
use Tests\Support\CrossFeatureTests;

/**
 * Authoritative registry composing all starter kit features, dependencies, and installer metadata.
 */
final class FeatureRegistry
{
    /**
     * @return array<string, FeatureDefinition>
     */
    public static function authFeatures(): array
    {
        return AuthFeatures::all();
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function optionalModules(): array
    {
        return OptionalModules::all();
    }

    /**
     * @return array<string, FeatureDefinition>
     */
    public static function all(): array
    {
        return array_merge(self::authFeatures(), self::optionalModules());
    }

    public static function get(string $key): FeatureDefinition
    {
        $all = self::all();

        if (! isset($all[$key])) {
            throw new InvalidArgumentException(sprintf('Unknown feature key [%s].', $key));
        }

        return $all[$key];
    }

    /**
     * @return list<string>
     */
    public static function allComposerPackages(): array
    {
        $packages = ['laravel/chisel', 'spatie/laravel-data'];
        foreach (self::all() as $feature) {
            foreach ($feature->composerPackages as $pkg) {
                $packages[] = $pkg;
            }
        }

        return array_values(array_unique($packages));
    }

    /**
     * @return list<string>
     */
    public static function allFrontendPackages(): array
    {
        $packages = [];
        foreach (self::all() as $feature) {
            foreach ($feature->frontendPackages as $pkg) {
                $packages[] = $pkg;
            }
        }

        return array_values(array_unique($packages));
    }

    /**
     * Transform registry into the exact shape expected by chisel-paths.php and backward-compatible tests.
     *
     * @return array<string, mixed>
     */
    public static function toPathsArray(): array
    {
        return [
            'auth' => [
                'login' => 'resources/js/pages/session/create.tsx',
                'welcome' => 'resources/js/pages/welcome.tsx',
                'register' => 'resources/js/pages/user/create.tsx',
                'user_dir' => 'resources/js/pages/user',
                'verify_email' => 'resources/js/pages/user-email-verification-notification/create.tsx',
                'verify_email_dir' => 'resources/js/pages/user-email-verification-notification',
                'two_factor_show' => 'resources/js/pages/user-two-factor-authentication/show.tsx',
                'two_factor_challenge' => 'resources/js/pages/user-two-factor-authentication-challenge/show.tsx',
                'two_factor_dirs' => [
                    'resources/js/pages/user-two-factor-authentication',
                    'resources/js/pages/user-two-factor-authentication-challenge',
                ],
                'two_factor_files' => [
                    'resources/js/components/two-factor-setup-modal.tsx',
                    'resources/js/components/two-factor-recovery-codes.tsx',
                    'resources/js/hooks/use-two-factor-auth.ts',
                ],
                'settings_layout' => 'resources/js/layouts/settings/layout.tsx',
                'profile' => 'resources/js/pages/user-profile/edit.tsx',
                'auth_types' => 'resources/js/types/auth.ts',
            ],

            'authorization' => [
                'files' => [
                    'resources/js/components/can.tsx',
                    'resources/js/hooks/use-authorization.ts',
                ],
                'composer_package' => 'spatie/laravel-permission',
                'empty_dirs' => [
                    'tests/Feature/Authorization',
                ],
            ],

            'settings' => [
                'pages' => [
                    'resources/js/pages/settings/application/edit.tsx',
                ],
                'types' => 'resources/js/types/settings.ts',
                'empty_dirs' => [
                    'resources/js/pages/settings/application',
                    'tests/Feature/Settings',
                ],
            ],

            'user_management' => [
                'components' => [
                    'resources/js/components/users/user-status-badge.tsx',
                ],
                'pages' => [
                    'resources/js/pages/users/index.tsx',
                    'resources/js/pages/users/create.tsx',
                    'resources/js/pages/users/edit.tsx',
                ],
                'types' => 'resources/js/types/users.ts',
                'empty_dirs' => [
                    'app/Actions/Users',
                    'app/Data/Users',
                    'app/Http/Controllers/Users',
                    'app/Http/Requests/Users',
                    'app/Queries/Users',
                    'resources/js/components/users',
                    'resources/js/pages/users',
                    'tests/Feature/Users',
                ],
            ],

            'localization' => [
                'components' => [
                    'resources/js/components/language-selector.tsx',
                ],
                'types' => 'resources/js/types/localization.ts',
                'composer_package' => 'erag/laravel-lang-sync-inertia',
                'frontend_package' => '@erag/lang-sync-inertia',
                'extra_lang_files' => [
                    'lang/fr/common.php',
                    'lang/fr/localization.php',
                    'lang/fr/notifications.php',
                    'lang/fr/reports.php',
                    'lang/fr/audit.php',
                    'lang/ar/common.php',
                    'lang/ar/localization.php',
                    'lang/ar/notifications.php',
                    'lang/ar/reports.php',
                    'lang/ar/audit.php',
                ],
                'empty_dirs' => [
                    'tests/Feature/Localization',
                    'lang/fr',
                    'lang/ar',
                ],
            ],

            'notifications' => [
                'components' => [
                    'resources/js/components/notifications/notification-bell.tsx',
                    'resources/js/components/notifications/notification-dropdown.tsx',
                    'resources/js/components/notifications/notification-empty-state.tsx',
                    'resources/js/components/notifications/notification-item.tsx',
                ],
                'pages' => [
                    'resources/js/pages/notifications/index.tsx',
                    'resources/js/pages/settings/notifications/edit.tsx',
                ],
                'types' => 'resources/js/types/notifications.ts',
                'empty_dirs' => [
                    'app/Actions/Notifications',
                    'app/Http/Requests/Notifications',
                    'app/Notifications',
                    'resources/js/components/notifications',
                    'resources/js/pages/notifications',
                    'resources/js/pages/settings/notifications',
                    'tests/Feature/Notifications',
                    'tests/Unit/Actions/Notifications',
                    'tests/Unit/Notifications',
                ],
            ],

            'audit_trails' => [
                'components' => [
                    'resources/js/components/audit-trails/audit-trail-detail.tsx',
                ],
                'pages' => [
                    'resources/js/pages/audit-trails/index.tsx',
                ],
                'types' => 'resources/js/types/audit-trails.ts',
                'empty_dirs' => [
                    'app/Actions/AuditTrails',
                    'app/Http/Controllers/AuditTrails',
                    'app/Http/Requests/AuditTrails',
                    'app/Queries/AuditTrails',
                    'resources/js/components/audit-trails',
                    'resources/js/pages/audit-trails',
                    'tests/Feature/AuditTrails',
                ],
            ],

            'reporting' => [
                'components' => [
                    'resources/js/components/reports/report-summary-cards.tsx',
                    'resources/js/components/reports/report-chart.tsx',
                    'resources/js/components/reports/report-date-range-filter.tsx',
                ],
                'pages' => [
                    'resources/js/pages/reports/index.tsx',
                    'resources/js/pages/reports/users.tsx',
                    'resources/js/pages/reports/audit.tsx',
                ],
                'types' => 'resources/js/types/reports.ts',
                'files' => [
                    'app/Enums/ReportCategory.php',
                    'app/Enums/ReportType.php',
                    'app/Data/Reporting/ReportSummaryCardData.php',
                    'app/Data/Reporting/ReportTimeSeriesPointData.php',
                    'app/Data/Reporting/ReportBreakdownItemData.php',
                    'app/Data/Reporting/ReportMetadataData.php',
                    'app/Queries/Reporting/UserReportQuery.php',
                    'app/Queries/Reporting/AuditReportQuery.php',
                    'app/Queries/Reporting/ExportUserReportStream.php',
                    'app/Queries/Reporting/ExportAuditReportStream.php',
                    'app/Http/Requests/Reporting/UserReportRequest.php',
                    'app/Http/Requests/Reporting/AuditReportRequest.php',
                    'app/Http/Requests/Reporting/ExportReportRequest.php',
                    'app/Http/Controllers/Reporting/ReportIndexController.php',
                    'app/Http/Controllers/Reporting/UserReportController.php',
                    'app/Http/Controllers/Reporting/ExportUserReportController.php',
                    'app/Http/Controllers/Reporting/AuditReportController.php',
                    'app/Http/Controllers/Reporting/ExportAuditReportController.php',
                    'lang/en/reports.php',
                    'lang/fr/reports.php',
                    'lang/ar/reports.php',
                    'tests/Unit/Reporting/ReportTypeTest.php',
                    'tests/Unit/Reporting/ReportCategoryTest.php',
                    'tests/Feature/Reporting/UserReportQueryTest.php',
                    'tests/Feature/Reporting/AuditReportQueryTest.php',
                    'tests/Feature/Reporting/ReportIndexControllerTest.php',
                    'tests/Feature/Reporting/UserReportControllerTest.php',
                    'tests/Feature/Reporting/AuditReportControllerTest.php',
                    'tests/Feature/Reporting/ReportingLocalizationTest.php',
                ],
                'empty_dirs' => [
                    'app/Data/Reporting',
                    'app/Queries/Reporting',
                    'app/Http/Requests/Reporting',
                    'app/Http/Controllers/Reporting',
                    'resources/js/components/reports',
                    'resources/js/pages/reports',
                    'tests/Unit/Reporting',
                    'tests/Feature/Reporting',
                ],
            ],

            'data' => [
                'config' => 'config/data.php',
                'composer_package' => 'spatie/laravel-data',
            ],

            'dependencies' => (function (): array {
                $deps = [];
                foreach (self::optionalModules() as $key => $feature) {
                    if ($feature->dependencies !== []) {
                        $deps[$key] = $feature->dependencies;
                    }
                }

                uksort($deps, function (string $a, string $b) use ($deps): int {
                    $countDiff = count($deps[$b]) <=> count($deps[$a]);
                    if ($countDiff !== 0) {
                        return $countDiff;
                    }

                    $order = ['audit-trails' => 1, 'user-management' => 2, 'settings' => 3];

                    return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
                });

                return $deps;
            })(),

            'cross_feature_tests' => class_exists(CrossFeatureTests::class)
                ? CrossFeatureTests::all()
                : [],

            'chisel' => [
                'files' => Cleanup::files(),
                'empty_dirs' => Cleanup::directories(),
            ],
        ];
    }
}
