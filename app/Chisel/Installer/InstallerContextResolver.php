<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;
use JsonException;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use RuntimeException;

use function Laravel\Prompts\multiselect;

/**
 * Resolves interactive/non-interactive inputs and environment configurations into an immutable InstallerContext.
 */
final class InstallerContextResolver
{
    /**
     * Determines whether installer hooks should be deferred based on flags and answers.
     */
    public static function shouldDeferInstallerHooks(Command $command): bool
    {
        if ($command->option('answers') !== null || self::hasEnvironmentAnswers()) {
            return false;
        }

        return self::installerFlag('LARAVEL_INSTALLER_DEFER_HOOKS');
    }

    /**
     * Checks if answers were supplied through environment variables.
     */
    public static function hasEnvironmentAnswers(): bool
    {
        return self::environmentAnswers() !== null;
    }

    /**
     * Retrieves answers supplied through environment variables if available.
     */
    public static function environmentAnswers(): ?string
    {
        $chisel = Env::get('CHISEL_ANSWERS', Request::server('CHISEL_ANSWERS') ?? (getenv('CHISEL_ANSWERS') ?: null));
        if (is_string($chisel) && mb_trim($chisel) !== '') {
            return $chisel;
        }

        $installer = Env::get('LARAVEL_INSTALLER_ANSWERS', Request::server('LARAVEL_INSTALLER_ANSWERS') ?? (getenv('LARAVEL_INSTALLER_ANSWERS') ?: null));
        if (is_string($installer) && mb_trim($installer) !== '') {
            return $installer;
        }

        return null;
    }

    /**
     * Checks if Node/frontend installations should be skipped.
     */
    public static function shouldSkipNode(): bool
    {
        return self::installerFlag('LARAVEL_INSTALLER_NO_NODE');
    }

    /**
     * Checks a boolean installer flag from environment or server variables.
     */
    public static function installerFlag(string $name): bool
    {
        return filter_var(
            Env::get($name, Request::server($name) ?? getenv($name)),
            FILTER_VALIDATE_BOOL,
        );
    }

    /**
     * Resolves the complete InstallerContext for the command run.
     *
     * @throws JsonException
     */
    public static function resolve(Command $command, Script $script, bool $isMockedScript, bool $isInteractive = true): InstallerContext
    {
        /** @var array<string, mixed> $paths */
        $paths = require base_path('chisel-paths.php');

        $rawAnswers = $command->option('answers');
        if (! is_string($rawAnswers) || $rawAnswers === '') {
            $rawAnswers = self::environmentAnswers();
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
            ->interactive($isInteractive)
            ->withAnswers($providedAnswers);

        $answers = $pendingAnswers->toArray();

        $rawModules = (array) ($answers['optional_modules'] ?? []);
        $selectedModules = array_values(array_filter($rawModules, is_string(...)));

        $rawAuth = (array) ($answers['auth_features'] ?? []);
        $selectedAuthFeatures = array_values(array_filter($rawAuth, is_string(...)));

        $hasAuthorization = in_array('authorization', $selectedModules, true);

        $adminName = $command->option('admin-name')
            ?? Env::get('CHISEL_ADMIN_NAME', Request::server('CHISEL_ADMIN_NAME') ?? getenv('CHISEL_ADMIN_NAME'));
        $adminEmail = $command->option('admin-email')
            ?? Env::get('CHISEL_ADMIN_EMAIL', Request::server('CHISEL_ADMIN_EMAIL') ?? getenv('CHISEL_ADMIN_EMAIL'));
        $adminPassword = $command->option('admin-password')
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

        $isNonInteractive = $command->option('answers') !== null || ! $isInteractive || self::hasEnvironmentAnswers();
        $skipNode = self::shouldSkipNode();

        return new InstallerContext(
            providedAnswers: $providedAnswers,
            answers: $answers,
            paths: $paths,
            selectedModules: $selectedModules,
            selectedAuthFeatures: $selectedAuthFeatures,
            hasAuthorization: $hasAuthorization,
            adminName: $adminName,
            adminEmail: $adminEmail,
            adminPassword: $adminPassword,
            isNonInteractive: $isNonInteractive,
            skipNode: $skipNode,
            isMockedScript: $isMockedScript,
        );
    }
}
