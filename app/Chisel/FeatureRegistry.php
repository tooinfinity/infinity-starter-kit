<?php

declare(strict_types=1);

namespace App\Chisel;

use App\Chisel\Features\AuthFeatures;
use App\Chisel\Features\CrossFeatureTests;
use App\Chisel\Features\OptionalModules;
use App\Chisel\Installer\Cleanup;
use InvalidArgumentException;

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
        $authorization = self::optionalModules()['authorization'];
        $settings = self::optionalModules()['settings'];
        $userManagement = self::optionalModules()['user-management'];
        $localization = self::optionalModules()['localization'];
        $notifications = self::optionalModules()['notifications'];
        $auditTrails = self::optionalModules()['audit-trails'];
        $reporting = self::optionalModules()['reporting'];

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
                ...self::derivedPackageKeys($authorization),
                'empty_dirs' => $authorization->emptyDirectories,
            ],

            'settings' => [
                'pages' => [
                    'resources/js/pages/settings/application/edit.tsx',
                ],
                'types' => 'resources/js/types/settings.ts',
                'empty_dirs' => $settings->emptyDirectories,
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
                'empty_dirs' => $userManagement->emptyDirectories,
            ],

            'localization' => [
                'components' => [
                    'resources/js/components/language-selector.tsx',
                ],
                'types' => 'resources/js/types/localization.ts',
                ...self::derivedPackageKeys($localization),
                'extra_lang_files' => array_values(array_filter(
                    $localization->exclusiveFiles,
                    fn (string $file): bool => str_starts_with($file, 'lang/fr/') || str_starts_with($file, 'lang/ar/'),
                )),
                'empty_dirs' => $localization->emptyDirectories,
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
                'empty_dirs' => $notifications->emptyDirectories,
            ],

            'audit_trails' => [
                'components' => [
                    'resources/js/components/audit-trails/audit-trail-detail.tsx',
                ],
                'pages' => [
                    'resources/js/pages/audit-trails/index.tsx',
                ],
                'types' => 'resources/js/types/audit-trails.ts',
                'empty_dirs' => $auditTrails->emptyDirectories,
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
                'files' => $reporting->exclusiveFiles,
                'empty_dirs' => $reporting->emptyDirectories,
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

            'cross_feature_tests' => CrossFeatureTests::all(),

            'chisel' => [
                'files' => Cleanup::files(),
                'empty_dirs' => Cleanup::directories(),
            ],
        ];
    }

    /**
     * Derives the legacy composer_package(s) and frontend_package keys from a FeatureDefinition.
     *
     * @return array<string, string|list<string>>
     */
    private static function derivedPackageKeys(FeatureDefinition $feature): array
    {
        $keys = [];
        if (isset($feature->composerPackages[0])) {
            $keys['composer_package'] = $feature->composerPackages[0];
        }

        if (isset($feature->frontendPackages[0])) {
            $keys['frontend_package'] = $feature->frontendPackages[0];
        }

        return $keys;
    }
}
