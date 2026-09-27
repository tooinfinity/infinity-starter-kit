<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;
use JsonException;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

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
        if ($this->shouldDeferInstallerHooks()) {
            return self::SUCCESS;
        }

        if (! file_exists(base_path('chisel.php')) || ! file_exists(base_path('chisel-paths.php'))) {
            return self::SUCCESS;
        }

        /** @var array<string, mixed> $paths */
        $paths = require base_path('chisel-paths.php');

        /** @var Script $script */
        $script = app()->bound(Script::class) ? resolve(Script::class) : require base_path('chisel.php');

        $providedAnswers = $this->option('answers') === null
            ? []
            : json_decode((string) $this->option('answers'), true, 512, JSON_THROW_ON_ERROR);

        throw_unless(is_array($providedAnswers), RuntimeException::class, 'The --answers option must decode to a JSON object.');

        /** @var array<string, mixed> $providedAnswers */
        $pendingAnswers = $script
            ->collectAnswers()
            ->onQuestion(fn (Question $question): array => multiselect(
                label: $question->label,
                options: $question->options,
                default: $question->default ?? [],
                required: $question->required,
                hint: $question->hint,
            ))
            ->interactive($this->input->isInteractive())
            ->withAnswers($providedAnswers);

        $answers = $pendingAnswers->toArray();

        /** @var array<string, list<string>> $dependencyMap */
        $dependencyMap = $paths['dependencies'] ?? [];
        chiselValidateDependencies($answers, $dependencyMap);

        $script->chisel($answers);

        $selectedModules = (array) ($answers['optional_modules'] ?? []);
        $hasAuthorization = in_array('authorization', $selectedModules, true);

        if ($hasAuthorization) {
            $authStatus = $this->call('authorization:setup');
            if ($authStatus !== self::SUCCESS) {
                $this->components->error('Authorization setup failed.');

                return $authStatus;
            }

            $adminStatus = $this->setupAdminUser($providedAnswers);
            if ($adminStatus !== self::SUCCESS) {
                $this->components->error('Administrator setup failed.');

                return $adminStatus;
            }
        }

        if (file_exists(base_path('vendor/bin/pint'))) {
            chiselRun(['vendor/bin/pint', '--format', 'agent'], 'Format PHP Code', base_path());
        }

        if (file_exists(base_path('artisan'))) {
            chiselRun([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form', '--no-interaction'], 'Generate Wayfinder Resources', base_path());
        }

        $skipNode = $this->shouldSkipNode();

        if (! $skipNode) {
            $this->installFrontendDependencies();
            $this->buildAssets();
            $this->lintAssets();
        }

        $this->performFinalValidation();
        $this->cleanupInstaller($paths);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $providedAnswers
     */
    private function setupAdminUser(array $providedAnswers): int
    {
        $adminName = $this->option('admin-name');
        $adminEmail = $this->option('admin-email');
        $adminPassword = $this->option('admin-password');

        /** @var array<string, mixed> $adminData */
        $adminData = is_array($providedAnswers['admin'] ?? null) ? $providedAnswers['admin'] : [];
        $adminName = is_string($adminName) && $adminName !== ''
            ? $adminName
            : (is_string($adminData['name'] ?? null) ? $adminData['name'] : (is_string($providedAnswers['admin_name'] ?? null) ? $providedAnswers['admin_name'] : null));
        $adminEmail = is_string($adminEmail) && $adminEmail !== ''
            ? $adminEmail
            : (is_string($adminData['email'] ?? null) ? $adminData['email'] : (is_string($providedAnswers['admin_email'] ?? null) ? $providedAnswers['admin_email'] : null));
        $adminPassword = is_string($adminPassword) && $adminPassword !== ''
            ? $adminPassword
            : (is_string($adminData['password'] ?? null) ? $adminData['password'] : (is_string($providedAnswers['admin_password'] ?? null) ? $providedAnswers['admin_password'] : null));

        $adminParams = [];
        if ($adminName !== null) {
            $adminParams['--name'] = $adminName;
        }

        if ($adminEmail !== null) {
            $adminParams['--email'] = $adminEmail;
        }

        if ($adminPassword !== null) {
            $adminParams['--password'] = $adminPassword;
        }

        if ($adminEmail !== null) {
            return $this->call('admin:setup', $adminParams);
        }

        if ($this->option('answers') === null && $this->input->isInteractive()) {
            return $this->call('admin:setup', $adminParams);
        }

        $this->components->info('No administrator credentials provided in non-interactive mode; skipping administrator creation.');

        return self::SUCCESS;
    }

    private function shouldDeferInstallerHooks(): bool
    {
        if ($this->option('answers') !== null) {
            return false;
        }

        return $this->installerFlag('LARAVEL_INSTALLER_DEFER_HOOKS');
    }

    private function shouldSkipNode(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        return $this->installerFlag('LARAVEL_INSTALLER_NO_NODE');
    }

    private function installerFlag(string $name): bool
    {
        return filter_var(
            Env::get($name, Request::server($name) ?? getenv($name)),
            FILTER_VALIDATE_BOOL,
        );
    }

    private function installFrontendDependencies(): void
    {
        $npm = Chisel::in(base_path())->npm();
        $packageManager = $npm->packageManager();

        spin(
            fn () => $npm->install(),
            "Installing dependencies with {$packageManager->value}...",
        );
    }

    private function buildAssets(): void
    {
        $npm = Chisel::in(base_path())->npm();

        spin(
            fn () => $npm->run('build'),
            'Building assets...',
        );
    }

    private function lintAssets(): void
    {
        $npm = Chisel::in(base_path())->npm();

        spin(
            fn () => $npm->run('lint'),
            'Linting frontend assets...',
        );
    }

    private function performFinalValidation(): void
    {
        $requiredFiles = [
            base_path('composer.json'),
            base_path('package.json'),
            base_path('bootstrap/app.php'),
            base_path('routes/web.php'),
            base_path('app/Models/User.php'),
        ];

        foreach ($requiredFiles as $file) {
            throw_unless(
                file_exists($file),
                RuntimeException::class,
                "Final validation failed: expected file [{$file}] does not exist.",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $paths
     */
    private function cleanupInstaller(array $paths): void
    {
        if (app()->runningUnitTests() && ! $this->installerFlag('CHISEL_RUN_CLEANUP')) {
            return;
        }

        /** @var array{chisel?: array{files?: list<string>, empty_dirs?: list<string>}} $paths */
        chiselCleanup(base_path(), $paths);
    }
}
