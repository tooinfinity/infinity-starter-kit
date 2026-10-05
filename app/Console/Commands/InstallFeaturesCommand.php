<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chisel\Installer\DependencyValidator;
use App\Chisel\Installer\GeneratedApplicationConfigurator;
use App\Chisel\Installer\GeneratedApplicationValidator;
use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\InstallerContextResolver;
use App\Chisel\Installer\PostScaffoldRunner;
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
        {--admin-name= : Name of the administrator user}
        {--admin-email= : Email of the administrator user}
        {--admin-password= : Password for the administrator user}')]
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

        $isMockedScript = app()->bound(Script::class);

        /** @var Script $script */
        $script = $isMockedScript ? resolve(Script::class) : require base_path('chisel.php');

        $context = InstallerContextResolver::resolve($this, $script, $isMockedScript, $this->input->isInteractive());

        DependencyValidator::validate($context->answers);

        if (! $this->runChisel($script, $context)) {
            return self::FAILURE;
        }

        $configStatus = GeneratedApplicationConfigurator::configure($this, $context, $this->components);
        if ($configStatus !== self::SUCCESS) {
            return $configStatus;
        }

        if (! $this->runPostScaffoldingTools($context)) {
            return self::FAILURE;
        }

        GeneratedApplicationValidator::injectTestFailureIfRequested();

        GeneratedApplicationValidator::validatePreCleanup(base_path());
        $didCleanup = $this->cleanupInstaller($context);
        GeneratedApplicationValidator::validatePostCleanup($context, $didCleanup, base_path());

        return self::SUCCESS;
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
        chiselCleanup(base_path(), $paths);

        return true;
    }
}
