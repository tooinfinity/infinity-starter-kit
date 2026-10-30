<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureRegistry;
use App\Chisel\LegacyPathsAdapter;

test('LegacyPathsAdapter toArray produces expected structure matching FeatureRegistry', function (): void {
    $paths = LegacyPathsAdapter::toArray();

    expect($paths)->toHaveKeys([
        'auth',
        'authorization',
        'settings',
        'user_management',
        'localization',
        'notifications',
        'audit_trails',
        'reporting',
        'data',
        'dependencies',
        'cross_feature_tests',
        'chisel',
    ]);
});

test('LegacyPathsAdapter serializeModule mechanically serializes arbitrary FeatureDefinition without hard-coded blocks', function (): void {
    $customFeature = new FeatureDefinition(
        key: 'custom-module',
        label: 'Custom Module',
        sectionMarker: 'custom-module',
        markerFiles: ['routes/web.php'],
        exclusiveFiles: [
            'resources/js/components/custom/widget.tsx',
            'resources/js/pages/custom/index.tsx',
            'resources/js/types/custom.ts',
            'lang/fr/custom.php',
            'lang/ar/custom.php',
            'app/Actions/CustomAction.php',
            'app/Models/CustomModel.php',
        ],
        emptyDirectories: ['resources/js/pages/custom'],
        composerPackages: ['vendor/custom-composer-pkg'],
        frontendPackages: ['@custom/frontend-pkg'],
        dependencies: ['authorization'],
    );

    $serialized = LegacyPathsAdapter::serializeModule($customFeature);

    expect($serialized['composer_package'])->toBe('vendor/custom-composer-pkg')
        ->and($serialized['frontend_package'])->toBe('@custom/frontend-pkg')
        ->and($serialized['empty_dirs'])->toBe(['resources/js/pages/custom'])
        ->and($serialized['components'])->toBe(['resources/js/components/custom/widget.tsx'])
        ->and($serialized['pages'])->toBe(['resources/js/pages/custom/index.tsx'])
        ->and($serialized['types'])->toBe('resources/js/types/custom.ts')
        ->and($serialized['extra_lang_files'])->toBe(['lang/fr/custom.php', 'lang/ar/custom.php'])
        ->and($serialized['files'])->toBe($customFeature->exclusiveFiles);
});

test('LegacyPathsAdapter serializeAuth derives paths from AuthFeatures definition', function (): void {
    $authPaths = LegacyPathsAdapter::serializeAuth();

    expect($authPaths)->toHaveKeys([
        'login',
        'welcome',
        'register',
        'user_dir',
        'verify_email',
        'verify_email_dir',
        'two_factor_show',
        'two_factor_challenge',
        'two_factor_dirs',
        'two_factor_files',
        'settings_layout',
        'profile',
        'auth_types',
    ]);

    expect($authPaths['login'])->toBe('resources/js/pages/session/create.tsx')
        ->and($authPaths['welcome'])->toBe('resources/js/pages/welcome.tsx')
        ->and($authPaths['register'])->toBe('resources/js/pages/user/create.tsx')
        ->and($authPaths['two_factor_files'])->toBe([
            'resources/js/components/two-factor-setup-modal.tsx',
            'resources/js/components/two-factor-recovery-codes.tsx',
            'resources/js/hooks/use-two-factor-auth.ts',
        ]);
});

test('LegacyPathsAdapter dependencies matches FeatureRegistry optional module dependencies', function (): void {
    $deps = LegacyPathsAdapter::dependencies();

    foreach (FeatureRegistry::optionalModules() as $key => $feature) {
        if ($feature->dependencies !== []) {
            expect($deps)->toHaveKey($key)
                ->and($deps[$key])->toBe($feature->dependencies);
        } else {
            expect(isset($deps[$key]))->toBeFalse();
        }
    }
});

test('LegacyPathsAdapter serializeModule handles multiple type definition files', function (): void {
    $featureWithMultipleTypes = new FeatureDefinition(
        key: 'multi-type-module',
        label: 'Multi Type Module',
        sectionMarker: 'multi-type-module',
        markerFiles: [],
        exclusiveFiles: [
            'resources/js/types/alpha.ts',
            'resources/js/types/beta.ts',
        ],
        emptyDirectories: [],
    );

    $serialized = LegacyPathsAdapter::serializeModule($featureWithMultipleTypes);

    expect($serialized['types'])->toBe([
        'resources/js/types/alpha.ts',
        'resources/js/types/beta.ts',
    ]);
});
