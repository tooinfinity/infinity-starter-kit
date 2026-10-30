<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureRegistry;
use App\Chisel\Features\CrossFeatureTests;

/**
 * Resolves feature definitions, dependencies, and lifecycle steps into a dry-run InstallationPlan.
 */
final class InstallerPlanner
{
    public static function plan(InstallerContext $context, string $basePath, bool $isFresh = false): InstallationPlan
    {
        $allFeatures = FeatureRegistry::all();

        $selectedAuth = $context->selectedAuthFeatures;
        $selectedModules = $context->selectedModules;
        $allSelected = array_values(array_unique(array_merge($selectedAuth, $selectedModules)));
        $unselected = array_values(array_diff(array_keys($allFeatures), $allSelected));
        sort($unselected);

        // Build dependency graph and requiredBy relationships
        $dependencyGraph = [];
        $requiredBy = [];

        foreach ($allSelected as $featureKey) {
            $definition = FeatureRegistry::get($featureKey);
            $dependencyGraph[$featureKey] = $definition->dependencies;

            foreach ($definition->dependencies as $dep) {
                if (in_array($dep, $allSelected, true)) {
                    $requiredBy[$dep][] = $featureKey;
                }
            }
        }

        foreach ($requiredBy as $dep => $dependents) {
            $requiredBy[$dep] = array_values(array_unique($dependents));
            sort($requiredBy[$dep]);
        }

        ksort($requiredBy);

        $dependencyDerivedFeatures = array_keys($requiredBy);
        sort($dependencyDerivedFeatures);

        // Collect pruned files
        $prunedFiles = [];
        $prunedDirs = [];
        $modifiedFiles = [];

        foreach ($unselected as $unselectedKey) {
            $definition = FeatureRegistry::get($unselectedKey);
            foreach ($definition->exclusiveFiles as $file) {
                $prunedFiles[] = $file;
            }

            foreach ($definition->emptyDirectories as $dir) {
                $prunedDirs[] = $dir;
            }

            foreach ($definition->markerFiles as $file) {
                $modifiedFiles[] = $file;
            }
        }

        // Cross-feature tests
        foreach (CrossFeatureTests::all() as $entry) {
            if (count(array_intersect($entry['features'], $selectedModules)) !== count($entry['features'])) {
                foreach ($entry['files'] as $file) {
                    $prunedFiles[] = $file;
                }
            }
        }

        // Shared conditional pruning: password controller
        if (! in_array('password-reset', $selectedAuth, true) && ! in_array('password-update', $selectedAuth, true)) {
            $prunedFiles[] = 'app/Http/Controllers/UserPasswordController.php';
            $prunedDirs[] = 'resources/js/pages/user-password';
            $modifiedFiles[] = 'routes/web.php';
        }

        // Shared conditional pruning: spatie/laravel-data
        $hasDataConsumer = in_array('user-management', $selectedModules, true) || in_array('reporting', $selectedModules, true);
        if (! $hasDataConsumer) {
            $prunedFiles[] = 'config/data.php';
        }

        // Specific modified files for unselected features
        if (! in_array('email-verification', $selectedAuth, true)) {
            $modifiedFiles[] = 'app/Models/User.php';
            $modifiedFiles[] = 'resources/js/pages/user-profile/edit.tsx';
        }

        if (! in_array('two-factor', $selectedAuth, true)) {
            $modifiedFiles[] = 'app/Models/User.php';
        }

        if (! in_array('user-management', $selectedModules, true)) {
            $modifiedFiles[] = 'app/Models/User.php';
        }

        if (! in_array('localization', $selectedModules, true)) {
            $modifiedFiles[] = 'app/Models/User.php';
        }

        if (! in_array('authorization', $selectedModules, true)) {
            $modifiedFiles[] = 'phpstan.neon';
        }

        $prunedFiles = array_values(array_unique($prunedFiles));
        sort($prunedFiles);

        $prunedDirs = array_values(array_unique($prunedDirs));
        sort($prunedDirs);

        $modifiedFiles = array_values(array_unique($modifiedFiles));
        sort($modifiedFiles);

        // Composer & Frontend packages
        $retainedComposer = [];
        $removedComposer = [];

        if (in_array('authorization', $selectedModules, true)) {
            $retainedComposer[] = 'spatie/laravel-permission';
        } else {
            $removedComposer[] = 'spatie/laravel-permission';
        }

        if ($hasDataConsumer) {
            $retainedComposer[] = 'spatie/laravel-data';
        } else {
            $removedComposer[] = 'spatie/laravel-data';
        }

        if (in_array('localization', $selectedModules, true)) {
            $retainedComposer[] = 'erag/laravel-lang-sync-inertia';
        } else {
            $removedComposer[] = 'erag/laravel-lang-sync-inertia';
        }

        $removedComposer[] = 'laravel/chisel'; // Always pruned during installer cleanup

        $retainedFrontend = [];
        $removedFrontend = [];

        if (in_array('localization', $selectedModules, true)) {
            $retainedFrontend[] = '@erag/lang-sync-inertia';
        } else {
            $removedFrontend[] = '@erag/lang-sync-inertia';
        }

        // Database migrations
        $migrationCommand = $isFresh ? 'php artisan migrate:fresh --force' : 'php artisan migrate --force';

        // Configuration phases
        $configurationPhases = [];
        if ($context->hasAuthorization) {
            $configurationPhases['Authorization Setup'] = 'php artisan authorization:setup (Create permissions & Super Admin role)';

            $hasExplicitCredentials = $context->adminEmail !== null
                || $context->adminName !== null
                || $context->adminPassword !== null
                || (is_array($context->providedAnswers['admin'] ?? null) && $context->providedAnswers['admin'] !== []);

            if ($hasExplicitCredentials) {
                $emailDesc = $context->adminEmail !== null ? "email: {$context->adminEmail}" : 'credentials provided';
                $configurationPhases['Administrator Setup'] = "php artisan admin:setup ({$emailDesc})";
            } elseif (! $context->isNonInteractive) {
                $configurationPhases['Administrator Setup'] = 'php artisan admin:setup (prompt interactively)';
            } else {
                $configurationPhases['Administrator Setup'] = 'Skipped (no administrator credentials provided in non-interactive mode)';
            }
        } else {
            $configurationPhases['Authorization Setup'] = 'Skipped (authorization module not selected)';
            $configurationPhases['Administrator Setup'] = 'Skipped (authorization module not selected)';
        }

        // Post-scaffolding tooling
        $postScaffoldCommands = [
            'PHP Code Formatting' => 'vendor/bin/pint --format agent',
            'Wayfinder Route Generation' => 'php artisan wayfinder:generate --with-form --no-interaction',
        ];

        if (! $context->skipNode) {
            $postScaffoldCommands['Frontend Dependencies'] = 'bun install';
            $postScaffoldCommands['Frontend Build'] = 'bun run build';
            $postScaffoldCommands['Frontend Lint'] = 'bun run lint';
        } else {
            $postScaffoldCommands['Frontend Pipeline'] = 'Skipped (LARAVEL_INSTALLER_NO_NODE enabled)';
        }

        // Validation & cleanup phases
        $validationPhases = [
            'Pre-Cleanup Validation' => 'GeneratedApplicationValidator::validatePreCleanup (verify core application structure)',
            'Configuration Cleanup' => 'ConfigCleaner (clean phpunit.xml and phpstan.neon exclusions)',
            'Composer Manifest Cleanup' => 'ComposerManifestPruner (clean post-create scripts from composer.json)',
            'Composer Lock Synchronization' => 'ComposerLockSynchronizer (synchronize composer.lock with pruned dependencies)',
            'Installer Machinery Cleanup' => 'Cleanup::clean (remove installer files and empty directories)',
            'Post-Cleanup Validation' => 'GeneratedApplicationValidator::validatePostCleanup (verify clean post-installation state)',
        ];

        return new InstallationPlan(
            explicitAuthFeatures: $selectedAuth,
            explicitModules: $selectedModules,
            selectedFeatures: $allSelected,
            unselectedFeatures: $unselected,
            dependencyGraph: $dependencyGraph,
            requiredBy: $requiredBy,
            dependencyDerivedFeatures: $dependencyDerivedFeatures,
            prunedFiles: $prunedFiles,
            prunedDirectories: $prunedDirs,
            modifiedFiles: $modifiedFiles,
            retainedComposerPackages: $retainedComposer,
            removedComposerPackages: $removedComposer,
            retainedFrontendPackages: $retainedFrontend,
            removedFrontendPackages: $removedFrontend,
            migrationCommand: $migrationCommand,
            configurationPhases: $configurationPhases,
            postScaffoldCommands: $postScaffoldCommands,
            validationPhases: $validationPhases,
            cleanupFiles: Cleanup::files(),
            cleanupDirectories: Cleanup::directories(),
        );
    }
}
