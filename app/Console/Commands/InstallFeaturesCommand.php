<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chisel\Installer\Cleanup;
use App\Chisel\Installer\DependencyValidator;
use App\Chisel\Installer\GeneratedApplicationConfigurator;
use App\Chisel\Installer\GeneratedApplicationValidator;
use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\InstallerContextResolver;
use App\Chisel\Installer\InstallerPlanner;
use App\Chisel\Installer\InstallerState;
use App\Chisel\Installer\PostScaffoldRunner;
use App\Chisel\Installer\PreflightValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessFailedException;
use JsonException;
use Laravel\Chisel\Script;
use RuntimeException;
use Throwable;

#[Description('Choose which starter kit features to keep')]
#[Signature('install:features
        {--answers= : JSON string of answers to skip interactive prompts}
        {--answers-file= : Path to a JSON file of answers to skip interactive prompts}
        {--preset= : Name of the preset to install (minimal, standard, full)}
        {--admin-name= : Name of the administrator user}
        {--admin-email= : Email of the administrator user}
        {--admin-password= : Password for the administrator user}
        {--fresh : Drop all tables and re-run all migrations}
        {--dry-run : Preview all planned mutations without making any changes}')]
final class InstallFeaturesCommand extends Command
{
    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(): int
    {
        if (InstallerContextResolver::shouldDeferInstallerHooks($this)) {
            return self::SUCCESS;
        }

        if (! file_exists(base_path('chisel.php')) || ! file_exists(base_path('chisel-paths.php'))) {
            return self::SUCCESS;
        }

        $basePath = base_path();
        $state = InstallerState::load($basePath);

        if ($state instanceof InstallerState) {
            $this->components->info(sprintf(
                'Resuming previous installation from phase [%s].',
                $state->failedPhase ?? $state->currentPhase,
            ));
        }

        $isMockedScript = app()->bound(Script::class);

        /** @var Script $script */
        $script = $isMockedScript ? resolve(Script::class) : require base_path('chisel.php');

        $context = InstallerContextResolver::resolve($this, $script, $isMockedScript, $this->input->isInteractive());

        DependencyValidator::validate($context->answers);

        if ($state instanceof InstallerState && $state->answersConflict($context)) {
            $this->components->error('Conflicting answers on installer retry.');
            $this->outputConflictDetails($state, $context);

            return self::FAILURE;
        }

        if ($context->isDryRun) {
            $plan = InstallerPlanner::plan($context, base_path(), (bool) $this->option('fresh'));
            $plan->render($this, $this->components);

            return self::SUCCESS;
        }

        if (! $state instanceof InstallerState || ! $state->hasCompleted('preflight')) {
            PreflightValidator::validate($context, $basePath);
            $state ??= InstallerState::initialize($basePath, $context);
            $state->markPhaseComplete($basePath, 'preflight');
        }

        if (! $state->hasCompleted('prune')) {
            $state->setCurrentPhase($basePath, 'prune');

            if (! $this->runChisel($script, $context)) {
                $state->recordFailure($basePath, 'prune', 'Feature pruning failed.');
                $this->outputActionableRecovery($state);

                return self::FAILURE;
            }

            $state->markPhaseComplete($basePath, 'prune');
        } else {
            $this->components->info('Feature pruning already completed (skipped on retry).');
        }

        try {
            GeneratedApplicationValidator::injectTestFailureIfRequested('post_chisel');
        } catch (Throwable $throwable) {
            $state->recordFailure($basePath, 'post_chisel', $context->redact($throwable->getMessage()));
            $this->outputActionableRecovery($state);

            throw $throwable;
        }

        if (! $state->hasCompleted('migrate')) {
            $state->setCurrentPhase($basePath, 'migrate');

            if (! $this->migrateDatabase($context)) {
                $state->recordFailure($basePath, 'migrate', 'Database migration failed.');
                $this->outputActionableRecovery($state);

                return self::FAILURE;
            }

            $state->markPhaseComplete($basePath, 'migrate');
        } else {
            $this->components->info('Database migration already completed.');
        }

        try {
            GeneratedApplicationValidator::injectTestFailureIfRequested('post_migration');
        } catch (Throwable $throwable) {
            $state->recordFailure($basePath, 'post_migration', $context->redact($throwable->getMessage()));
            $this->outputActionableRecovery($state);

            throw $throwable;
        }

        if (! $state->hasCompleted('configure')) {
            $state->setCurrentPhase($basePath, 'configure');

            $configStatus = GeneratedApplicationConfigurator::configure($this, $context, $this->components);
            if ($configStatus !== self::SUCCESS) {
                $state->recordFailure($basePath, 'configure', 'Application configuration failed.');
                $this->outputActionableRecovery($state);

                return $configStatus;
            }

            $state->markPhaseComplete($basePath, 'configure');
        } else {
            $this->components->info('Application configuration already completed.');
        }

        if (! $state->hasCompleted('post_scaffold')) {
            $state->setCurrentPhase($basePath, 'post_scaffold');

            if (! $this->runPostScaffoldingTools($context)) {
                $state->recordFailure($basePath, 'post_scaffold', 'Post-scaffolding tools failed.');
                $this->outputActionableRecovery($state);

                return self::FAILURE;
            }

            $state->markPhaseComplete($basePath, 'post_scaffold');
        } else {
            $this->components->twoColumnDetail('Post-scaffolding tools', 'already completed');
        }

        try {
            GeneratedApplicationValidator::injectTestFailureIfRequested('post_scaffold');
        } catch (Throwable $throwable) {
            $state->recordFailure($basePath, 'post_scaffold', $context->redact($throwable->getMessage()));
            $this->outputActionableRecovery($state);

            throw $throwable;
        }

        try {
            GeneratedApplicationValidator::injectTestFailureIfRequested('cleanup');
            GeneratedApplicationValidator::validatePreCleanup($basePath);
            $didCleanup = $this->cleanupInstaller($context);
            GeneratedApplicationValidator::validatePostCleanup($context, $didCleanup, $basePath);
        } catch (Throwable $throwable) {
            $state->recordFailure($basePath, 'cleanup', $context->redact($throwable->getMessage()));

            if ($this->hasInstallerFiles()) {
                $this->outputActionableRecovery($state);
            } else {
                $this->components->warn('Installation halted at phase [cleanup].');
                $this->components->info(sprintf('Checkpoint saved to [%s].', InstallerState::STATE_FILE));
                $this->newLine();
                $this->line('Installer files were already removed. Run `composer update --with-all-dependencies`, then remove `.installer-state.json`.');
                $this->line('To start fresh with a clean directory:');
                $this->line('  <comment>Delete this directory and run composer create-project again.</comment>');
            }

            throw $throwable;
        }

        $state->delete($basePath);

        return self::SUCCESS;
    }

    private function outputActionableRecovery(InstallerState $state): void
    {
        $phase = $state->failedPhase ?? $state->currentPhase;

        $this->components->warn(sprintf('Installation halted at phase [%s].', $phase));
        $this->components->info(sprintf('Checkpoint saved to [%s].', InstallerState::STATE_FILE));
        $this->newLine();
        $this->line('To retry and resume the installation:');
        $this->line('  <comment>php artisan install:features</comment>');
        $this->line('If administrator setup was requested, provide a new password with <comment>--admin-password</comment> or <comment>CHISEL_ADMIN_PASSWORD</comment>; passwords are not stored in the checkpoint.');
        $this->line('To start fresh with a clean directory:');
        $this->line('  <comment>Delete this directory and run composer create-project again.</comment>');
    }

    private function outputConflictDetails(InstallerState $state, InstallerContext $context): void
    {
        $savedAuth = $state->answers['auth_features'];
        $currentAuth = $context->selectedAuthFeatures;
        $savedModules = $state->answers['optional_modules'];
        $currentModules = $context->selectedModules;

        $this->newLine();
        $this->line('The project was already partially pruned with:');
        $this->line('  Authentication: '.($savedAuth !== [] ? implode(', ', $savedAuth) : '(none)'));
        $this->line('  Optional modules: '.($savedModules !== [] ? implode(', ', $savedModules) : '(none)'));
        $this->newLine();
        $this->line('Attempted retry with:');
        $this->line('  Authentication: '.($currentAuth !== [] ? implode(', ', $currentAuth) : '(none)'));
        $this->line('  Optional modules: '.($currentModules !== [] ? implode(', ', $currentModules) : '(none)'));
        $this->newLine();
        $this->line('The file structure has already been pruned according to the original selection and cannot be safely changed.');
        $this->line('To retry with the previous answers, run:');
        $this->line('  <comment>php artisan install:features</comment>');
        $this->line('If administrator setup was requested, provide a new password with <comment>--admin-password</comment> or <comment>CHISEL_ADMIN_PASSWORD</comment>; passwords are not stored in the checkpoint.');
        $this->newLine();
        $this->line('To start over with different features:');
        $this->line('  <comment>Delete this directory and run composer create-project again.</comment>');
    }

    private function runChisel(Script $script, InstallerContext $context): bool
    {
        try {
            $script->chisel($context->answers);

            return true;
        } catch (ProcessFailedException|RuntimeException $e) {
            $this->components->error($context->redact($e->getMessage()));

            return false;
        }
    }

    private function migrateDatabase(InstallerContext $context): bool
    {
        if (($context->isMockedScript || app()->runningUnitTests()) && ! InstallerContextResolver::installerFlag('CHISEL_RUN_MIGRATIONS')) {
            return true;
        }

        try {
            $command = (bool) $this->option('fresh') ? 'migrate:fresh' : 'migrate';

            $status = $this->call($command, [
                '--force' => true,
            ]);

            if ($status !== self::SUCCESS) {
                $this->components->error('Database migration failed.');

                return false;
            }

            return true;
        } catch (Throwable $throwable) {
            $this->components->error('Database migration failed: '.$context->redact($throwable->getMessage()));

            return false;
        }
    }

    private function runPostScaffoldingTools(InstallerContext $context): bool
    {
        try {
            PostScaffoldRunner::run($context, base_path());

            return true;
        } catch (ProcessFailedException|RuntimeException $e) {
            $this->components->error($context->redact($e->getMessage()));

            return false;
        }
    }

    private function cleanupInstaller(InstallerContext $context): bool
    {
        if ($context->isMockedScript && ! InstallerContextResolver::installerFlag('CHISEL_RUN_CLEANUP')) {
            return false;
        }

        /** @var array{chisel?: array{files?: list<string>, empty_dirs?: list<string>}} $paths */
        $paths = $context->paths;
        Cleanup::clean(base_path(), $paths);

        return true;
    }

    private function hasInstallerFiles(): bool
    {
        clearstatcache();

        return file_exists(base_path('chisel.php')) || file_exists(app_path('Console/Commands/InstallFeaturesCommand.php'));
    }
}
