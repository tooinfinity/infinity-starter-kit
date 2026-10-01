<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Request;
use JsonException;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
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

        $isMockedScript = app()->bound(Script::class);

        /** @var Script $script */
        $script = $isMockedScript ? resolve(Script::class) : require base_path('chisel.php');

        $rawAnswers = $this->option('answers');
        if (! is_string($rawAnswers) || $rawAnswers === '') {
            $envAnswers = Env::get('CHISEL_ANSWERS', Request::server('CHISEL_ANSWERS') ?? getenv('CHISEL_ANSWERS'))
                ?? Env::get('LARAVEL_INSTALLER_ANSWERS', Request::server('LARAVEL_INSTALLER_ANSWERS') ?? getenv('LARAVEL_INSTALLER_ANSWERS'));
            $rawAnswers = is_string($envAnswers) ? $envAnswers : null;
        }

        $providedAnswers = ($rawAnswers === null || $rawAnswers === '')
            ? []
            : json_decode($rawAnswers, true, 512, JSON_THROW_ON_ERROR);

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

        try {
            $script->chisel($answers);
        } catch (ProcessFailedException|RuntimeException $e) {
            $this->components->error($this->redactSecrets($e->getMessage(), $providedAnswers));

            return self::FAILURE;
        }

        $selectedModules = (array) ($answers['optional_modules'] ?? []);
        $hasAuthorization = in_array('authorization', $selectedModules, true);

        if ($hasAuthorization) {
            try {
                $authStatus = $this->call('authorization:setup');
            } catch (Throwable $e) {
                $this->components->error('Authorization setup failed: '.$this->redactSecrets($e->getMessage(), $providedAnswers));

                return self::FAILURE;
            }

            if ($authStatus !== self::SUCCESS) {
                $this->components->error('Authorization setup failed.');

                return $authStatus;
            }

            try {
                $adminStatus = $this->setupAdminUser($providedAnswers);
            } catch (Throwable $e) {
                $this->components->error('Administrator setup failed: '.$this->redactSecrets($e->getMessage(), $providedAnswers));

                return self::FAILURE;
            }

            if ($adminStatus !== self::SUCCESS) {
                $this->components->error('Administrator setup failed.');

                return $adminStatus;
            }
        }

        try {
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
        } catch (ProcessFailedException|RuntimeException $e) {
            $this->components->error($this->redactSecrets($e->getMessage(), $providedAnswers));

            return self::FAILURE;
        }

        $this->performFinalValidation();
        $didCleanup = $this->cleanupInstaller($paths, $isMockedScript);
        $this->performPostCleanupValidation($answers, $paths, $didCleanup);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $providedAnswers
     */
    private function setupAdminUser(array $providedAnswers): int
    {
        $adminName = $this->option('admin-name')
            ?? Env::get('CHISEL_ADMIN_NAME', Request::server('CHISEL_ADMIN_NAME') ?? getenv('CHISEL_ADMIN_NAME'));
        $adminEmail = $this->option('admin-email')
            ?? Env::get('CHISEL_ADMIN_EMAIL', Request::server('CHISEL_ADMIN_EMAIL') ?? getenv('CHISEL_ADMIN_EMAIL'));
        $adminPassword = $this->option('admin-password')
            ?? Env::get('CHISEL_ADMIN_PASSWORD', Request::server('CHISEL_ADMIN_PASSWORD') ?? getenv('CHISEL_ADMIN_PASSWORD'));

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

        if ($adminEmail !== null || $adminName !== null || $adminPassword !== null || $adminData !== []) {
            if ($this->option('answers') !== null || ! $this->input->isInteractive() || $this->hasEnvironmentAnswers()) {
                $adminParams['--no-interaction'] = true;
            }

            return $this->call('admin:setup', $adminParams);
        }

        if ($this->option('answers') === null && ! $this->hasEnvironmentAnswers() && $this->input->isInteractive()) {
            return $this->call('admin:setup', $adminParams);
        }

        $this->components->info('No administrator credentials provided in non-interactive mode; skipping administrator creation.');

        return self::SUCCESS;
    }

    private function shouldDeferInstallerHooks(): bool
    {
        if ($this->option('answers') !== null || $this->hasEnvironmentAnswers()) {
            return false;
        }

        return $this->installerFlag('LARAVEL_INSTALLER_DEFER_HOOKS');
    }

    private function hasEnvironmentAnswers(): bool
    {
        $raw = Env::get('CHISEL_ANSWERS', Request::server('CHISEL_ANSWERS') ?? getenv('CHISEL_ANSWERS'))
            ?? Env::get('LARAVEL_INSTALLER_ANSWERS', Request::server('LARAVEL_INSTALLER_ANSWERS') ?? getenv('LARAVEL_INSTALLER_ANSWERS'));

        return is_string($raw) && mb_trim($raw) !== '';
    }

    private function shouldSkipNode(): bool
    {
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
            fn () => Process::path(base_path())->forever()->run($packageManager->installCommand())->throw(),
            "Installing dependencies with {$packageManager->value}...",
        );
    }

    private function buildAssets(): void
    {
        $npm = Chisel::in(base_path())->npm();
        $packageManager = $npm->packageManager();

        spin(
            fn () => Process::path(base_path())->forever()->run($packageManager->runCommand('build'))->throw(),
            'Building assets...',
        );
    }

    private function lintAssets(): void
    {
        $npm = Chisel::in(base_path())->npm();
        $packageManager = $npm->packageManager();

        spin(
            fn () => Process::path(base_path())->forever()->run($packageManager->runCommand('lint'))->throw(),
            'Linting frontend assets...',
        );
    }

    /**
     * @throws JsonException
     */
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

        $composerData = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        throw_unless(
            is_array($composerData) && isset($composerData['require']) && is_array($composerData['require']),
            RuntimeException::class,
            'Final validation failed: composer.json does not contain a valid require object.',
        );
        $packageData = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        throw_unless(
            is_array($packageData) && isset($packageData['scripts']) && is_array($packageData['scripts']),
            RuntimeException::class,
            'Final validation failed: package.json does not contain a valid scripts object.',
        );
    }

    /**
     * @param  array<string, mixed>  $paths
     */
    private function cleanupInstaller(array $paths, bool $isMockedScript): bool
    {
        if ($isMockedScript && ! $this->installerFlag('CHISEL_RUN_CLEANUP')) {
            return false;
        }

        /** @var array{chisel?: array{files?: list<string>, empty_dirs?: list<string>}} $paths */
        chiselCleanup(base_path(), $paths);

        return true;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $paths
     *
     * @throws JsonException
     */
    private function performPostCleanupValidation(array $answers, array $paths, bool $cleanedUp): void
    {
        $this->performFinalValidation();

        $neonPath = base_path('phpstan.neon');
        if (file_exists($neonPath)) {
            $neonContent = (string) file_get_contents($neonPath);
            $normalizedNeon = preg_replace_callback(
                '/^\t+/m',
                fn (array $m): string => str_repeat('    ', mb_strlen($m[0])),
                $neonContent,
            ) ?? $neonContent;

            try {
                $parsedNeon = Yaml::parse($normalizedNeon);
            } catch (Throwable $e) {
                throw new RuntimeException('Post-cleanup validation failed: phpstan.neon failed to parse: '.$e->getMessage(), 0, $e);
            }

            throw_unless(
                is_array($parsedNeon) && isset($parsedNeon['parameters']) && is_array($parsedNeon['parameters']),
                RuntimeException::class,
                'Post-cleanup validation failed: phpstan.neon does not contain valid parameters.',
            );

            if ($cleanedUp) {
                throw_if(
                    str_contains($neonContent, 'chisel.php'),
                    RuntimeException::class,
                    'Post-cleanup validation failed: phpstan.neon still references chisel.php.',
                );

                throw_if(
                    array_key_exists('bootstrapFiles', $parsedNeon['parameters']) && empty($parsedNeon['parameters']['bootstrapFiles']),
                    RuntimeException::class,
                    'Post-cleanup validation failed: phpstan.neon contains an empty bootstrapFiles section.',
                );
            }
        }

        $phpunitPath = base_path('phpunit.xml');
        if (file_exists($phpunitPath)) {
            $phpunitContent = (string) file_get_contents($phpunitPath);
            $xml = @simplexml_load_string($phpunitContent);
            throw_if(
                $xml === false,
                RuntimeException::class,
                'Post-cleanup validation failed: phpunit.xml is not valid XML.',
            );

            if ($cleanedUp) {
                throw_if(
                    str_contains($phpunitContent, 'InstallFeaturesCommand'),
                    RuntimeException::class,
                    'Post-cleanup validation failed: phpunit.xml still references InstallFeaturesCommand.',
                );
            }
        }

        if (! $cleanedUp) {
            return;
        }

        $chiselConfig = is_array($paths['chisel'] ?? null) ? $paths['chisel'] : [];
        $rawChiselFiles = is_array($chiselConfig['files'] ?? null) ? $chiselConfig['files'] : [
            'app/Console/Commands/InstallFeaturesCommand.php',
            'app/Console/Commands/SetupAuthorizationCommand.php',
            'app/Console/Commands/SetupAdminUserCommand.php',
            'chisel.php',
            'chisel-paths.php',
        ];

        foreach ($rawChiselFiles as $relFile) {
            if (! is_string($relFile)) {
                continue;
            }

            throw_if(
                file_exists(base_path($relFile)),
                RuntimeException::class,
                "Post-cleanup validation failed: installer artifact [{$relFile}] was not removed.",
            );
        }

        $decodedComposer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        $composerData = is_array($decodedComposer) ? $decodedComposer : [];
        $composerRequire = is_array($composerData['require'] ?? null) ? $composerData['require'] : [];
        $composerRequireDev = is_array($composerData['require-dev'] ?? null) ? $composerData['require-dev'] : [];

        throw_if(
            isset($composerRequire['laravel/chisel']) || isset($composerRequireDev['laravel/chisel']),
            RuntimeException::class,
            'Post-cleanup validation failed: laravel/chisel is still present in composer.json.',
        );

        $composerJsonRaw = (string) file_get_contents(base_path('composer.json'));
        throw_if(
            str_contains($composerJsonRaw, 'install:features'),
            RuntimeException::class,
            'Post-cleanup validation failed: composer.json still references install:features.',
        );

        $selectedModules = (array) ($answers['optional_modules'] ?? []);
        if (! in_array('authorization', $selectedModules, true)) {
            throw_if(
                isset($composerRequire['spatie/laravel-permission']),
                RuntimeException::class,
                'Post-cleanup validation failed: spatie/laravel-permission was not removed when authorization is disabled.',
            );
        }

        if (! in_array('localization', $selectedModules, true)) {
            throw_if(
                isset($composerRequire['erag/laravel-lang-sync-inertia']),
                RuntimeException::class,
                'Post-cleanup validation failed: erag/laravel-lang-sync-inertia was not removed when localization is disabled.',
            );

            $decodedPackage = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
            $packageData = is_array($decodedPackage) ? $decodedPackage : [];
            $pkgDeps = is_array($packageData['dependencies'] ?? null) ? $packageData['dependencies'] : [];
            $pkgDevDeps = is_array($packageData['devDependencies'] ?? null) ? $packageData['devDependencies'] : [];

            throw_if(
                isset($pkgDeps['@erag/lang-sync-inertia']) || isset($pkgDevDeps['@erag/lang-sync-inertia']),
                RuntimeException::class,
                'Post-cleanup validation failed: @erag/lang-sync-inertia was not removed from package.json when localization is disabled.',
            );
        }

        $staleInstallerTerms = [
            'InstallFeaturesCommand',
            'SetupAuthorizationCommand',
            'SetupAdminUserCommand',
            'chisel.php',
            'chisel-paths.php',
            'Laravel\\Chisel',
            'laravel/chisel',
            'install:features',
        ];

        $scanDirs = ['app', 'bootstrap/app.php', 'config', 'database', 'routes', 'resources', 'tests'];
        foreach ($scanDirs as $relTarget) {
            $fullTarget = base_path($relTarget);
            if (! file_exists($fullTarget)) {
                continue;
            }

            $filesToScan = is_file($fullTarget)
                ? [new SplFileInfo($fullTarget)]
                : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullTarget));

            /** @var SplFileInfo $fileInfo */
            foreach ($filesToScan as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }

                $content = (string) file_get_contents($fileInfo->getPathname());
                $relPath = str_replace(base_path().'/', '', $fileInfo->getPathname());

                throw_if(str_contains($content, '@chisel-') || str_contains($content, '@end-chisel-'), RuntimeException::class, "Post-cleanup validation failed: unhandled chisel marker in [{$relPath}].");

                foreach ($staleInstallerTerms as $term) {
                    throw_if(str_contains($content, $term), RuntimeException::class, "Post-cleanup validation failed: stale installer reference [{$term}] found in [{$relPath}].");
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $providedAnswers
     */
    private function redactSecrets(string $message, array $providedAnswers): string
    {
        $secrets = [];

        $optionPassword = $this->option('admin-password');
        if (is_string($optionPassword) && $optionPassword !== '') {
            $secrets[] = $optionPassword;
        }

        $envPassword = Env::get('CHISEL_ADMIN_PASSWORD', Request::server('CHISEL_ADMIN_PASSWORD') ?? getenv('CHISEL_ADMIN_PASSWORD'));
        if (is_string($envPassword) && $envPassword !== '') {
            $secrets[] = $envPassword;
        }

        if (is_array($providedAnswers['admin'] ?? null) && is_string($providedAnswers['admin']['password'] ?? null) && $providedAnswers['admin']['password'] !== '') {
            $secrets[] = $providedAnswers['admin']['password'];
        }

        if (is_string($providedAnswers['admin_password'] ?? null) && $providedAnswers['admin_password'] !== '') {
            $secrets[] = $providedAnswers['admin_password'];
        }

        foreach ($secrets as $secret) {
            $message = str_replace($secret, '[REDACTED]', $message);
        }

        return $message;
    }
}
