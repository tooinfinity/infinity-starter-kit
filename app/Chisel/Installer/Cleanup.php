<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Laravel\Chisel\Chisel;

/**
 * Removes Chisel installer machinery and synchronizes configuration files after successful installation.
 */
final class Cleanup
{
    /**
     * @return list<string>
     */
    public static function files(): array
    {
        return [
            'app/Console/Commands/InstallFeaturesCommand.php',
            'app/Console/Commands/SetupAuthorizationCommand.php',
            'app/Console/Commands/SetupAdminUserCommand.php',
            'chisel.php',
            'chisel-paths.php',
            'app/Chisel/FeatureDefinition.php',
            'app/Chisel/FeatureRegistry.php',
            'app/Chisel/Features/AuthFeatures.php',
            'app/Chisel/Features/OptionalModules.php',
            'app/Chisel/Installer/DependencyValidator.php',
            'app/Chisel/Installer/InstallerContext.php',
            'app/Chisel/Installer/InstallerContextResolver.php',
            'app/Chisel/Installer/PostScaffoldRunner.php',
            'app/Chisel/Installer/GeneratedApplicationValidator.php',
            'app/Chisel/Installer/GeneratedApplicationConfigurator.php',
            'app/Chisel/Installer/ComposerManifestPruner.php',
            'app/Chisel/Installer/ComposerLockSynchronizer.php',
            'app/Chisel/Installer/FrontendPackagePruner.php',
            'app/Chisel/Installer/DirectoryPruner.php',
            'app/Chisel/Installer/ConfigCleaner.php',
            'app/Chisel/Installer/Cleanup.php',
            'app/Chisel/Installer/FeaturePruner.php',
            'tests/Support/CrossFeatureTests.php',
            'tests/Unit/Chisel/DirectoryPruningTest.php',
            'tests/Unit/Chisel/JsonPruningTest.php',
            'tests/Unit/Chisel/FeatureRegistryTest.php',
            'tests/Unit/Chisel/DependencyValidatorTest.php',
            'tests/Unit/Chisel/InstallerContextTest.php',
            'tests/Unit/Chisel/FeaturePrunerTest.php',
            'tests/Unit/Chisel/CleanupTest.php',
            'tests/Unit/Chisel/ComposerManifestPrunerTest.php',
            'tests/Unit/Chisel/ComposerLockSynchronizerTest.php',
            'tests/Feature/Chisel/DependencyValidationTest.php',
            'tests/Feature/Chisel/FeatureCombinationTest.php',
            'tests/Feature/Chisel/GeneratedProjectLifecycleTest.php',
            'tests/Feature/Chisel/ComposerCreateProjectLifecycleTest.php',
            'tests/Feature/Chisel/InstallFeaturesCommandTest.php',
            'tests/Feature/Chisel/MarkerIntegrityTest.php',
            'tests/Feature/Chisel/RegistryIntegrityTest.php',
            'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function directories(): array
    {
        return [
            'app/Chisel/Installer',
            'app/Chisel/Features',
            'app/Chisel',
            'tests/Support',
            'tests/Unit/Chisel',
            'tests/Feature/Chisel',
        ];
    }

    /**
     * @param  array{
     *     chisel?: array{
     *         files?: list<string>,
     *         empty_dirs?: list<string>,
     *     },
     * }  $paths
     */
    public static function clean(string $directory, array $paths): void
    {
        // Preload installer classes before their files are removed from disk
        class_exists(ConfigCleaner::class);
        class_exists(ComposerManifestPruner::class);
        class_exists(ComposerLockSynchronizer::class);
        class_exists(DirectoryPruner::class);

        ConfigCleaner::cleanPhpunit($directory);
        ConfigCleaner::cleanPhpstan($directory);
        ComposerManifestPruner::cleanPostCreate($directory);
        ComposerLockSynchronizer::sync($directory, $paths);

        $chisel = Chisel::in($directory);
        $chiselFiles = $paths['chisel']['files'] ?? self::files();

        $chisel->files(...$chiselFiles)->delete();

        DirectoryPruner::prune($directory, $paths['chisel']['empty_dirs'] ?? self::directories());
    }
}
