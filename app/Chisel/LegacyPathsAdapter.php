<?php

declare(strict_types=1);

namespace App\Chisel;

use App\Chisel\Features\CrossFeatureTests;
use App\Chisel\Installer\Cleanup;

/**
 * Adapter serializing FeatureRegistry definitions into the backward-compatible shape expected by chisel-paths.php.
 */
final class LegacyPathsAdapter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        $paths = [
            'auth' => self::serializeAuth(),
        ];

        foreach (FeatureRegistry::optionalModules() as $key => $feature) {
            $pathsKey = str_replace('-', '_', $key);
            $paths[$pathsKey] = self::serializeModule($feature);
        }

        $paths['data'] = [
            'config' => 'config/data.php',
            'composer_package' => 'spatie/laravel-data',
        ];

        $paths['dependencies'] = self::dependencies();
        $paths['cross_feature_tests'] = CrossFeatureTests::all();
        $paths['chisel'] = [
            'files' => Cleanup::files(),
            'empty_dirs' => Cleanup::directories(),
        ];

        return $paths;
    }

    /**
     * Mechanically serializes any optional FeatureDefinition into the legacy module paths shape.
     *
     * @return array<string, mixed>
     */
    public static function serializeModule(FeatureDefinition $feature): array
    {
        $data = [];

        $components = array_values(array_filter(
            $feature->exclusiveFiles,
            fn (string $file): bool => str_starts_with($file, 'resources/js/components/'),
        ));
        if ($components !== []) {
            $data['components'] = $components;
        }

        $pages = array_values(array_filter(
            $feature->exclusiveFiles,
            fn (string $file): bool => str_starts_with($file, 'resources/js/pages/'),
        ));
        if ($pages !== []) {
            $data['pages'] = $pages;
        }

        $types = array_values(array_filter(
            $feature->exclusiveFiles,
            fn (string $file): bool => str_starts_with($file, 'resources/js/types/'),
        ));
        if (count($types) === 1) {
            $data['types'] = $types[0];
        } elseif (count($types) > 1) {
            $data['types'] = $types;
        }

        if (isset($feature->composerPackages[0])) {
            $data['composer_package'] = $feature->composerPackages[0];
        }

        if (isset($feature->frontendPackages[0])) {
            $data['frontend_package'] = $feature->frontendPackages[0];
        }

        $extraLang = array_values(array_filter(
            $feature->exclusiveFiles,
            fn (string $file): bool => str_starts_with($file, 'lang/fr/') || str_starts_with($file, 'lang/ar/'),
        ));
        if ($extraLang !== []) {
            $data['extra_lang_files'] = $extraLang;
        }

        $data['files'] = $feature->exclusiveFiles;
        $data['empty_dirs'] = $feature->emptyDirectories;

        return $data;
    }

    /**
     * Derives the legacy auth path shape from authoritative AuthFeatures.
     *
     * @return array<string, mixed>
     */
    public static function serializeAuth(): array
    {
        $auth = FeatureRegistry::authFeatures();

        $findFirst = fn (array $files, string $pattern): string => array_find(
            $files,
            fn (string $file): bool => str_contains($file, $pattern),
        ) ?? '';

        return [
            'login' => $findFirst($auth['login']->exclusiveFiles, 'session/create.tsx'),
            'welcome' => $findFirst($auth['login']->markerFiles, 'welcome.tsx'),
            'register' => $findFirst($auth['registration']->exclusiveFiles, 'user/create.tsx'),
            'user_dir' => $auth['registration']->emptyDirectories[0] ?? 'resources/js/pages/user',
            'verify_email' => $findFirst($auth['email-verification']->exclusiveFiles, 'user-email-verification-notification/create.tsx'),
            'verify_email_dir' => $auth['email-verification']->emptyDirectories[0] ?? 'resources/js/pages/user-email-verification-notification',
            'two_factor_show' => $findFirst($auth['two-factor']->exclusiveFiles, 'user-two-factor-authentication/show.tsx'),
            'two_factor_challenge' => $findFirst($auth['two-factor']->exclusiveFiles, 'user-two-factor-authentication-challenge/show.tsx'),
            'two_factor_dirs' => $auth['two-factor']->emptyDirectories,
            'two_factor_files' => array_values(array_filter(
                $auth['two-factor']->exclusiveFiles,
                fn (string $file): bool => str_starts_with($file, 'resources/js/components/') || str_starts_with($file, 'resources/js/hooks/'),
            )),
            'settings_layout' => $findFirst($auth['two-factor']->markerFiles, 'settings/layout.tsx'),
            'profile' => $findFirst($auth['email-verification']->markerFiles, 'user-profile/edit.tsx'),
            'auth_types' => $findFirst($auth['email-verification']->markerFiles, 'types/auth.ts'),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function dependencies(): array
    {
        $deps = [];
        foreach (FeatureRegistry::optionalModules() as $key => $feature) {
            if ($feature->dependencies !== []) {
                $deps[$key] = $feature->dependencies;
            }
        }

        uksort($deps, function (string $a, string $b) use ($deps): int {
            $countDiff = count($deps[$b]) <=> count($deps[$a]);
            if ($countDiff !== 0) {
                return $countDiff;
            }

            return strcmp($a, $b);
        });

        return $deps;
    }
}
