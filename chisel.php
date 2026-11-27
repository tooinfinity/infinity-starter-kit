<?php

declare(strict_types=1);

if (! class_exists('Laravel\Chisel\Chisel')) {
    require getenv('LARAVEL_INSTALLER_AUTOLOADER') ?: __DIR__.'/vendor/autoload.php';
}

use App\Chisel\FeatureRegistry;
use App\Chisel\Features\CrossFeatureTests;
use App\Chisel\Installer\AiToolingPruner;
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
     * @param  array<string, string>  $env
     */
    function chiselRun(array $command, string $label, ?string $cwd = null, array $env = [], ?int $timeout = null): void
    {
        $directory = $cwd ?? __DIR__;

        if (class_exists(ProcessFacade::class) && ProcessFacade::getFacadeRoot() !== null) {
            $pendingProcess = ProcessFacade::path($directory);
            if ($env !== []) {
                $pendingProcess = $pendingProcess->env($env);
            }
            if ($timeout !== null) {
                $pendingProcess = $pendingProcess->timeout($timeout);
            } else {
                $pendingProcess = $pendingProcess->forever();
            }
            $pendingProcess->run($command)->throw();

            return;
        }

        if (! class_exists(Process::class)) {
            return;
        }

        $process = task(
            label: $label,
            keepSummary: true,
            callback: function (Logger $logger) use ($command, $directory, $env, $timeout) {
                $process = new Process($command, $directory, $env !== [] ? $env : null);
                $process->setTimeout($timeout);
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

if (! function_exists('chiselPruneAiTooling')) {
    function chiselPruneAiTooling(string $directory): void
    {
        AiToolingPruner::prune($directory);
    }
}

$directory = __DIR__;
$authFeatures = FeatureRegistry::authFeatures();
$optionalModules = FeatureRegistry::optionalModules();

$script = Chisel::script(__DIR__)
    ->questions([
        Question::multiselect(
            name: 'auth_features',
            label: 'Which authentication features would you like to enable?',
            options: FeatureRegistry::options($authFeatures),
            default: FeatureRegistry::defaults($authFeatures),
            hint: 'Use space to select, enter to confirm.',
        ),
        Question::multiselect(
            name: 'optional_modules',
            label: 'Which optional modules should be installed?',
            options: FeatureRegistry::options($optionalModules),
            default: FeatureRegistry::defaults($optionalModules),
            hint: 'Use space to select, enter to confirm.',
        ),
        Question::multiselect(
            name: 'ai_guidelines',
            label: 'Include AI assistant guidelines?',
            options: [
                'guidelines' => 'AI assistant guidelines (.claude, .cursor, AGENTS.md, ...)',
            ],
            default: [
                'guidelines',
            ],
            hint: 'Use space to select, enter to confirm.',
        ),
    ])
    ->apply(function (Chisel $chisel, array $answers): void {
        chiselValidateDependencies($answers);
    });

FeaturePruner::register(
    $script,
    'auth_features',
    $authFeatures,
    $directory,
    beforePrune: [
        'email-verification' => static function (Chisel $chisel): void {
            $chisel->php('app/Models/User.php')
                ->removeInterface('MustVerifyEmail');

            $chisel->file('app/Models/User.php')
                ->removeLinesContaining('@property-read CarbonInterface|null $email_verified_at');
        },
    ],
    afterPrune: [
        'email-verification' => static function (Chisel $chisel): void {
            $chisel->file('resources/js/pages/user-profile/edit.tsx')
                ->replace(
                    "import { Form, Head, Link, usePage } from '@inertiajs/react';",
                    "import { Form, Head, usePage } from '@inertiajs/react';",
                );
        },
    ],
);

FeaturePruner::register(
    $script,
    'optional_modules',
    $optionalModules,
    $directory,
    beforePrune: [
        'localization' => static function (Chisel $chisel): void {
            $chisel->php('app/Models/User.php')
                ->removeInterface('HasLocalePreference');

            $chisel->file('app/Models/User.php')
                ->removeLinesContaining('@property-read Locale|null $locale');
        },
    ],
    afterPrune: [
        'authorization' => static function () use ($directory): void {
            ConfigCleaner::cleanPhpstan($directory, removePermissionMigrationExclude: true);
        },
    ],
);

foreach (CrossFeatureTests::all() as $entry) {
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
    ->selected(
        'ai_guidelines',
        'guidelines',
        else: function (Chisel $chisel) use ($directory): void {
            chiselPruneAiTooling($directory);
        },
    )
    ->selectedAny(
        'auth_features',
        ['password-reset', 'password-update'],
        then: null,
        else: function (Chisel $chisel) use ($directory): void {
            $chisel->file('app/Http/Controllers/UserPasswordController.php')->delete();
            $chisel->file('routes/web.php')
                ->removeLinesContaining('use App\Http\Controllers\UserPasswordController;');
            chiselPruneEmptyDirectories($directory, ['resources/js/pages/user-password']);
        },
    )
    ->selectedAny(
        'auth_features',
        ['registration', 'password-reset'],
        then: null,
        else: function (Chisel $chisel): void {
            $chisel->file('resources/js/pages/session/create.tsx')
                ->removeLinesContaining("import TextLink from '@/components/text-link';");
        },
    )
    ->selectedAny(
        'optional_modules',
        ['user-management', 'audit-trails'],
        then: null,
        else: function (Chisel $chisel) use ($directory): void {
            $chisel->file('config/data.php')->delete();
            chiselRemoveComposerPackages($directory, 'spatie/laravel-data');
        },
    )

    ->apply(function () use ($directory): void {
        chiselSyncComposerLock($directory);
    });
