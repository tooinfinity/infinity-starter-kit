<?php

declare(strict_types=1);

if (! class_exists('Laravel\Chisel\Chisel')) {
    require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';
}

use App\Chisel\FeatureRegistry;
use App\Chisel\Installer\Cleanup;
use App\Chisel\Installer\ComposerLockSynchronizer;
use App\Chisel\Installer\ComposerManifestPruner;
use App\Chisel\Installer\ConfigCleaner;
use App\Chisel\Installer\DependencyValidator;
use App\Chisel\Installer\DirectoryPruner;
use App\Chisel\Installer\FeaturePruner;
use App\Chisel\Installer\FrontendPackagePruner;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Prompts\Support\Logger;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\task;

if (! function_exists('chiselRun')) {
    /**
     * Run a console command with progress indicator.
     *
     * @param  list<string>  $command
     */
    function chiselRun(array $command, string $label, ?string $cwd = null): void
    {
        $directory = $cwd ?? __DIR__;

        if (class_exists(ProcessFacade::class) && ProcessFacade::getFacadeRoot() !== null) {
            ProcessFacade::path($directory)->forever()->run($command)->throw();

            return;
        }

        if (! class_exists(Process::class)) {
            return;
        }

        $process = task(
            label: $label,
            keepSummary: true,
            callback: function (Logger $logger) use ($command, $directory) {
                $process = new Process($command, $directory);
                $process->setTimeout(null);
                $process->run(function ($type, $line) use ($logger): void {
                    $logger->line($line);
                });

                if ($process->isSuccessful()) {
                    $logger->success(implode(' ', $command));

                    return $process;
                }

                $logger->error(implode(' ', $command));
                $logger->error('Error output: '.mb_trim($process->getErrorOutput()));
                $logger->error('Chisel: Your project may be in a partially-modified state — review the output above before continuing.');

                return $process;
            },
        );

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Command "%s" failed with exit code %d: %s',
                implode(' ', $command),
                $process->getExitCode() ?? 1,
                mb_trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput()),
            ));
        }
    }
}

if (! function_exists('chiselSkipsNode')) {
    function chiselSkipsNode(): bool
    {
        return FrontendPackagePruner::skipsNode();
    }
}

if (! function_exists('chiselRemoveFrontendPackages')) {
    function chiselRemoveFrontendPackages(string $directory, Chisel $c, string ...$packages): void
    {
        FrontendPackagePruner::removePackages($directory, $c, ...$packages);
    }
}

if (! function_exists('chiselRemoveComposerPackages')) {
    function chiselRemoveComposerPackages(string $directory, string ...$packages): void
    {
        ComposerManifestPruner::removePackages($directory, ...$packages);
    }
}

if (! function_exists('chiselCleanComposerPostCreate')) {
    function chiselCleanComposerPostCreate(string $directory): void
    {
        ComposerManifestPruner::cleanPostCreate($directory);
    }
}

if (! function_exists('chiselSyncComposerLock')) {
    /**
     * @param  array<string, mixed>|null  $paths
     */
    function chiselSyncComposerLock(string $directory, ?array $paths = null): void
    {
        ComposerLockSynchronizer::sync($directory, $paths);
    }
}

if (! function_exists('chiselPruneEmptyDirectories')) {
    /**
     * @param  list<string>  $paths
     */
    function chiselPruneEmptyDirectories(string $directory, array $paths): void
    {
        DirectoryPruner::prune($directory, $paths);
    }
}

if (! function_exists('chiselValidateDependencies')) {
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>|null  $dependencyMap
     */
    function chiselValidateDependencies(array $answers, ?array $dependencyMap = null): void
    {
        DependencyValidator::validate($answers, $dependencyMap);
    }
}

if (! function_exists('chiselRemoveNeonListSectionItem')) {
    /**
     * @param  list<string>  $lines
     * @param  list<string>  $itemsToRemove
     * @return list<string>
     */
    function chiselRemoveNeonListSectionItem(array $lines, string $sectionKey, array $itemsToRemove): array
    {
        return ConfigCleaner::removeNeonListSectionItem($lines, $sectionKey, $itemsToRemove);
    }
}

if (! function_exists('chiselCleanPhpstanConfig')) {
    function chiselCleanPhpstanConfig(string $directory, bool $removePermissionMigrationExclude = false): void
    {
        ConfigCleaner::cleanPhpstan($directory, $removePermissionMigrationExclude);
    }
}

if (! function_exists('chiselCleanPhpunitConfig')) {
    function chiselCleanPhpunitConfig(string $directory): void
    {
        ConfigCleaner::cleanPhpunit($directory);
    }
}

if (! function_exists('chiselCleanup')) {
    /**
     * @param  array{
     *     chisel?: array{
     *         files?: list<string>,
     *         empty_dirs?: list<string>,
     *     },
     * }  $paths
     */
    function chiselCleanup(string $directory, array $paths): void
    {
        Cleanup::clean($directory, $paths);
    }
}

/** @var array<string, mixed> $paths */
$paths = require __DIR__.'/chisel-paths.php';

$directory = __DIR__;
$authFeatures = FeatureRegistry::authFeatures();
$optionalModules = FeatureRegistry::optionalModules();

$script = Chisel::script(__DIR__)
    ->questions([
        Question::multiselect(
            name: 'auth_features',
            label: 'Which authentication features would you like to enable?',
            options: [
                'registration' => 'Registration',
                'email-verification' => 'Email verification',
                'two-factor-authentication' => 'Two-factor authentication',
            ],
            default: ['registration', 'email-verification', 'two-factor-authentication'],
            hint: 'Use space to select, enter to confirm.',
        ),
        Question::multiselect(
            name: 'optional_modules',
            label: 'Which optional modules should be installed?',
            options: [
                'authorization' => 'Authorization',
                'settings' => 'Settings',
                'user-management' => 'User Management',
                'localization' => 'Localization',
                'notifications' => 'Notifications',
                'audit-trails' => 'Audit Trails',
                'reporting' => 'Reporting',
            ],
            default: [
                'authorization',
                'settings',
                'user-management',
                'localization',
                'notifications',
                'audit-trails',
                'reporting',
            ],
            hint: 'Use space to select, enter to confirm.',
        ),
    ])
    ->apply(function (Chisel $chisel, array $answers): void {
        chiselValidateDependencies($answers);
    })
    ->selected(
        'auth_features',
        'registration',
        then: function (Chisel $chisel) use ($authFeatures): void {
            // Marker: 'registration'
            FeaturePruner::applySelected($chisel, $authFeatures['registration']);
        },
        else: function (Chisel $chisel) use ($authFeatures, $directory): void {
            // Marker: 'registration'
            FeaturePruner::pruneUnselected($directory, $chisel, $authFeatures['registration']);
        },
    )
    ->selected(
        'auth_features',
        'email-verification',
        then: function (Chisel $chisel) use ($authFeatures): void {
            // Marker: 'email-verification'
            FeaturePruner::applySelected($chisel, $authFeatures['email-verification']);
        },
        else: function (Chisel $chisel) use ($authFeatures, $directory): void {
            $chisel->php('app/Models/User.php')
                ->removeInterface('MustVerifyEmail');

            $chisel->file('app/Models/User.php')
                ->removeLinesContaining('@property-read CarbonInterface|null $email_verified_at');

            // Marker: 'email-verification'
            FeaturePruner::pruneUnselected($directory, $chisel, $authFeatures['email-verification']);

            $chisel->file('resources/js/pages/user-profile/edit.tsx')
                ->replace(
                    "import { Form, Head, Link, usePage } from '@inertiajs/react';",
                    "import { Form, Head, usePage } from '@inertiajs/react';",
                );
        },
    )
    ->selected(
        'auth_features',
        'two-factor-authentication',
        then: function (Chisel $chisel) use ($authFeatures): void {
            // Marker: 'two-factor-authentication'
            FeaturePruner::applySelected($chisel, $authFeatures['two-factor-authentication']);
        },
        else: function (Chisel $chisel) use ($authFeatures, $directory): void {
            $chisel->file('app/Models/User.php')
                ->removeLinesContaining(
                    '@property-read string|null $two_factor_secret',
                    '@property-read string|null $two_factor_recovery_codes',
                    '@property-read CarbonInterface|null $two_factor_confirmed_at',
                    "'two_factor_secret',",
                    "'two_factor_recovery_codes',",
                );

            // Marker: 'two-factor-authentication'
            FeaturePruner::pruneUnselected($directory, $chisel, $authFeatures['two-factor-authentication']);
        },
    )
    ->selected(
        'optional_modules',
        'authorization',
        then: function (Chisel $chisel) use ($optionalModules): void {
            // Marker: 'roles-permissions'
            FeaturePruner::applySelected($chisel, $optionalModules['authorization']);
        },
        else: function (Chisel $chisel) use ($optionalModules, $directory): void {
            // Marker: 'roles-permissions'
            FeaturePruner::pruneUnselected($directory, $chisel, $optionalModules['authorization']);
            ConfigCleaner::cleanPhpstan($directory, removePermissionMigrationExclude: true);
        },
    )
    ->selected(
        'optional_modules',
        'settings',
        then: function (Chisel $chisel) use ($optionalModules): void {
            // Marker: 'settings'
            FeaturePruner::applySelected($chisel, $optionalModules['settings']);
        },
        else: function (Chisel $chisel) use ($optionalModules, $directory): void {
            // Marker: 'settings'
            FeaturePruner::pruneUnselected($directory, $chisel, $optionalModules['settings']);
        },
    )
    ->selected(
        'optional_modules',
        'user-management',
        then: function (Chisel $chisel) use ($optionalModules): void {
            // Marker: 'user-management'
            FeaturePruner::applySelected($chisel, $optionalModules['user-management']);
        },
        else: function (Chisel $chisel) use ($optionalModules, $directory): void {
            $chisel->file('app/Models/User.php')
                ->removeLinesContaining('@property-read bool $is_active');

            // Marker: 'user-management'
            FeaturePruner::pruneUnselected($directory, $chisel, $optionalModules['user-management']);
        },
    )
    ->selected(
        'optional_modules',
        'localization',
        then: function (Chisel $chisel) use ($optionalModules): void {
            // Marker: 'localization'
            FeaturePruner::applySelected($chisel, $optionalModules['localization']);
        },
        else: function (Chisel $chisel) use ($optionalModules, $directory): void {
            $chisel->php('app/Models/User.php')
                ->removeInterface('HasLocalePreference');

            $chisel->file('app/Models/User.php')
                ->removeLinesContaining('@property-read Locale|null $locale');

            // Marker: 'localization'
            FeaturePruner::pruneUnselected($directory, $chisel, $optionalModules['localization']);
        },
    )
    ->selected(
        'optional_modules',
        'notifications',
        then: function (Chisel $chisel) use ($optionalModules): void {
            // Marker: 'notifications'
            FeaturePruner::applySelected($chisel, $optionalModules['notifications']);
        },
        else: function (Chisel $chisel) use ($optionalModules, $directory): void {
            // Marker: 'notifications'
            FeaturePruner::pruneUnselected($directory, $chisel, $optionalModules['notifications']);
        },
    )
    ->selected(
        'optional_modules',
        'audit-trails',
        then: function (Chisel $chisel) use ($optionalModules): void {
            // Marker: 'audit-trails'
            FeaturePruner::applySelected($chisel, $optionalModules['audit-trails']);
        },
        else: function (Chisel $chisel) use ($optionalModules, $directory): void {
            // Marker: 'audit-trails'
            FeaturePruner::pruneUnselected($directory, $chisel, $optionalModules['audit-trails']);
        },
    )
    ->selected(
        'optional_modules',
        'reporting',
        then: function (Chisel $chisel) use ($optionalModules): void {
            // Marker: 'reporting'
            FeaturePruner::applySelected($chisel, $optionalModules['reporting']);
        },
        else: function (Chisel $chisel) use ($optionalModules, $directory): void {
            // Marker: 'reporting'
            FeaturePruner::pruneUnselected($directory, $chisel, $optionalModules['reporting']);
        },
    );

foreach ($paths['cross_feature_tests'] ?? [] as $entry) {
    $script->selectedAll(
        'optional_modules',
        $entry['features'],
        then: null,
        else: function (Chisel $chisel) use ($entry): void {
            $chisel->files(...$entry['files'])->delete();
        },
    );
}

return $script
    ->selectedAny(
        'optional_modules',
        ['user-management', 'reporting'],
        then: null,
        else: function (Chisel $chisel) use ($directory): void {
            $chisel->file('config/data.php')->delete();
            chiselRemoveComposerPackages($directory, 'spatie/laravel-data');
        },
    )
    ->apply(function () use ($directory, $paths): void {
        chiselSyncComposerLock($directory, $paths);
    });
