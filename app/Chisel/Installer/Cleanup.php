<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureRegistry;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Filesystem\PendingFiles;
use RuntimeException;
use Throwable;

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
            'app/Console/Commands/SetupAdminUserCommand.php',
            'app/Console/Commands/GenerateFeatureDocsCommand.php',
            'chisel.php',
            'chisel-paths.php',
            'app/Chisel/FeatureDefinition.php',
            'app/Chisel/FeatureRegistry.php',
            'app/Chisel/FeatureMatrixGenerator.php',
            'app/Chisel/ManifestExampleGenerator.php',
            'app/Chisel/LegacyPathsAdapter.php',
            'app/Chisel/Features/AuthFeatures.php',
            'app/Chisel/Features/OptionalModules.php',
            'app/Chisel/Features/CrossFeatureTests.php',
            'app/Chisel/Installer/DependencyValidator.php',
            'app/Chisel/Installer/InstallerContext.php',
            'app/Chisel/Installer/InstallerContextResolver.php',
            'app/Chisel/Installer/Profile.php',
            'app/Chisel/Installer/Preset.php',
            'app/Chisel/Installer/InstallerState.php',
            'app/Chisel/Installer/PreflightValidator.php',
            'app/Chisel/Installer/PostScaffoldRunner.php',
            'app/Chisel/Installer/GeneratedApplicationValidator.php',
            'app/Chisel/Installer/GeneratedApplicationConfigurator.php',
            'app/Chisel/Installer/GeneratedApplicationManifest.php',
            'app/Chisel/Installer/PerformanceBudget.php',
            'app/Chisel/Installer/ComposerManifestPruner.php',
            'app/Chisel/Installer/ComposerLockSynchronizer.php',
            'app/Chisel/Installer/FrontendPackagePruner.php',
            'app/Chisel/Installer/DirectoryPruner.php',
            'app/Chisel/Installer/ConfigCleaner.php',
            'app/Chisel/Installer/Cleanup.php',
            'app/Chisel/Installer/FeaturePruner.php',
            'app/Chisel/Installer/AiToolingPruner.php',
            'app/Chisel/Installer/InstallationPlan.php',
            'app/Chisel/Installer/InstallerPlanner.php',
            'app/Chisel/Installer/InstallerScenario.php',
            'tests/Support/CrossFeatureTests.php',
            'tests/Support/GeneratedProjectFixture.php',
            'tests/Support/GeneratedApplicationSmokePipeline.php',
            'tests/Unit/Chisel/AiToolingPrunerTest.php',
            'tests/Unit/Chisel/DirectoryPruningTest.php',
            'tests/Unit/Chisel/JsonPruningTest.php',
            'tests/Unit/Chisel/FeatureRegistryTest.php',
            'tests/Unit/Chisel/LegacyPathsAdapterTest.php',
            'tests/Unit/Chisel/DependencyValidatorTest.php',
            'tests/Unit/Chisel/InstallerContextTest.php',
            'tests/Unit/Chisel/ProfileTest.php',
            'tests/Unit/Chisel/PresetTest.php',
            'tests/Unit/Chisel/FeaturePrunerTest.php',
            'tests/Unit/Chisel/CleanupTest.php',
            'tests/Unit/Chisel/ComposerManifestPrunerTest.php',
            'tests/Unit/Chisel/ComposerLockSynchronizerTest.php',
            'tests/Unit/Chisel/GeneratedApplicationConfiguratorTest.php',
            'tests/Unit/Chisel/GeneratedApplicationValidatorTest.php',
            'tests/Unit/Chisel/GeneratedApplicationManifestTest.php',
            'tests/Unit/Chisel/InstallerContextResolverTest.php',
            'tests/Unit/Chisel/InstallationPlanTest.php',
            'tests/Unit/Chisel/InstallerPlannerTest.php',
            'tests/Feature/Chisel/AiGuidelinesInstallationTest.php',
            'tests/Unit/Chisel/InstallerStateTest.php',
            'tests/Unit/Chisel/PreflightValidatorTest.php',
            'tests/Feature/Chisel/DependencyValidationTest.php',
            'tests/Feature/Chisel/FeatureCombinationTest.php',
            'tests/Feature/Chisel/GeneratedProjectLifecycleTest.php',
            'tests/Feature/Chisel/GeneratedApplicationSmokeTest.php',
            'tests/Feature/Chisel/ComposerCreateProjectLifecycleTest.php',
            'tests/Feature/Chisel/InstallFeaturesCommandTest.php',
            'tests/Feature/Chisel/MarkerIntegrityTest.php',
            'tests/Feature/Chisel/RegistryIntegrityTest.php',
            'tests/Feature/Chisel/FeatureContractTest.php',
            'tests/Feature/Chisel/GenerateFeatureDocsCommandTest.php',
            'tests/Feature/Chisel/FeatureMatrixDocumentationTest.php',
            'tests/Feature/Chisel/ManifestDocumentationTest.php',
            'tests/Unit/Chisel/FeatureMatrixGeneratorTest.php',
            'tests/Unit/Chisel/ManifestExampleGeneratorTest.php',
            'tests/Unit/Chisel/PerformanceBudgetTest.php',
            'tests/Feature/Chisel/GeneratedApplicationPerformanceTest.php',
            'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
            'tests/Unit/Chisel/InstallerScenarioMatrixTest.php',
            'docs/installer-matrix.md',
            '.github/workflows/installer.yml',
            'tests/Unit/CiWorkflowIntegrityTest.php',
            '.ai/guidelines/app.chisel.blade.php',
            'scripts/test-feature-matrix.sh',
            'scripts/test-feature-matrix.php',
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
        FeatureRegistry::all();
        class_exists(Preset::class);
        class_exists(Profile::class);
        class_exists(ConfigCleaner::class);
        class_exists(ComposerManifestPruner::class);
        class_exists(ComposerLockSynchronizer::class);
        class_exists(DirectoryPruner::class);
        class_exists(AiToolingPruner::class);
        class_exists(InstallerState::class);
        class_exists(PreflightValidator::class);
        class_exists(GeneratedApplicationManifest::class);
        class_exists(Chisel::class);
        class_exists(PendingFiles::class);

        ConfigCleaner::cleanPhpunit($directory);
        ConfigCleaner::cleanPhpstan($directory);
        ComposerManifestPruner::cleanPostCreate($directory);

        $updateAiGuidelines = self::canUpdateAiGuidelines($directory);

        $chisel = Chisel::in($directory);
        $chiselFiles = $paths['chisel']['files'] ?? self::files();

        $chisel->files(...$chiselFiles)->delete();

        DirectoryPruner::prune($directory, $paths['chisel']['empty_dirs'] ?? self::directories());

        if ($updateAiGuidelines) {
            self::runAiGuidelinesUpdateSafely($directory);
        }

        try {
            ComposerLockSynchronizer::sync($directory, $paths);
        } catch (RuntimeException $runtimeException) {
            throw new RuntimeException($runtimeException->getMessage()."\nInstaller files were already removed. Run `composer update --with-all-dependencies`, then remove `.installer-state.json`.", $runtimeException->getCode(), previous: $runtimeException);
        }
    }

    public static function updateAiGuidelines(string $directory): void
    {
        if (self::canUpdateAiGuidelines($directory)) {
            self::runAiGuidelinesUpdateSafely($directory);
        }
    }

    private static function canUpdateAiGuidelines(string $directory): bool
    {
        if (! file_exists($directory.'/boost.json') || ! is_dir($directory.'/.ai/guidelines') || ! file_exists($directory.'/artisan') || ! is_dir($directory.'/vendor/laravel/boost')) {
            return false;
        }

        $command = [PHP_BINARY, 'artisan', 'list', '--raw', '--no-interaction'];
        $env = ['APP_ENV' => 'local'];

        $output = ProcessFacade::path($directory)->env($env)->run($command)->throw()->output();

        return preg_match('/^boost:update(?:\s|$)/m', $output) === 1;
    }

    private static function runAiGuidelinesUpdateSafely(string $directory): void
    {
        try {
            self::runAiGuidelinesUpdate($directory);
        } catch (Throwable) {
            // Guideline regeneration is best-effort; failure must not abort cleanup.
        }
    }

    private static function runAiGuidelinesUpdate(string $directory): void
    {
        chiselRun(
            [PHP_BINARY, 'artisan', 'boost:update', '--no-interaction', '--no-discover', '--ignore-skills'],
            'Update AI Guidelines',
            $directory,
            ['APP_ENV' => 'local'],
            timeout: 60,
        );
    }
}
