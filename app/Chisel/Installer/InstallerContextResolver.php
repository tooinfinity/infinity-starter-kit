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
        $hasCliAnswers = ($command->hasOption('answers') && $command->option('answers') !== null)
            || ($command->hasOption('answers-file') && $command->option('answers-file') !== null)
            || ($command->hasOption('preset') && $command->option('preset') !== null);

        if ($hasCliAnswers || self::hasEnvironmentAnswers() || self::hasEnvironmentAnswersFile() || self::hasEnvironmentPreset()) {
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
     * Checks if an answers file was supplied through environment variables.
     */
    public static function hasEnvironmentAnswersFile(): bool
    {
        return self::environmentAnswersFile() !== null;
    }

    /**
     * Retrieves the answers file path supplied through environment variables if available.
     */
    public static function environmentAnswersFile(): ?string
    {
        $chisel = Env::get('CHISEL_ANSWERS_FILE', Request::server('CHISEL_ANSWERS_FILE') ?? (getenv('CHISEL_ANSWERS_FILE') ?: null));
        if (is_string($chisel) && mb_trim($chisel) !== '') {
            return $chisel;
        }

        $installer = Env::get('LARAVEL_INSTALLER_ANSWERS_FILE', Request::server('LARAVEL_INSTALLER_ANSWERS_FILE') ?? (getenv('LARAVEL_INSTALLER_ANSWERS_FILE') ?: null));
        if (is_string($installer) && mb_trim($installer) !== '') {
            return $installer;
        }

        return null;
    }

    /**
     * Checks if a preset was supplied through environment variables.
     */
    public static function hasEnvironmentPreset(): bool
    {
        return self::environmentPreset() !== null;
    }

    /**
     * Retrieves the preset supplied through environment variables if available.
     */
    public static function environmentPreset(): ?string
    {
        $chisel = Env::get('CHISEL_PRESET', Request::server('CHISEL_PRESET') ?? (getenv('CHISEL_PRESET') ?: null));
        if (is_string($chisel) && mb_trim($chisel) !== '') {
            return $chisel;
        }

        $installer = Env::get('LARAVEL_INSTALLER_PRESET', Request::server('LARAVEL_INSTALLER_PRESET') ?? (getenv('LARAVEL_INSTALLER_PRESET') ?: null));
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
     * Loads and validates answers from a JSON file.
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function loadAnswersFromFile(string $filePath, bool $isOption = true): array
    {
        $trimmedPath = mb_trim($filePath);
        if ($trimmedPath === '') {
            $message = $isOption
                ? 'The --answers-file option must specify a non-empty file path.'
                : 'Answers file path cannot be empty.';

            throw new RuntimeException($message);
        }

        $resolvedPath = file_exists($trimmedPath) ? $trimmedPath : base_path($trimmedPath);

        if (! file_exists($resolvedPath)) {
            throw new RuntimeException(sprintf('Answers file [%s] does not exist.', $filePath));
        }

        if (! is_readable($resolvedPath) || ($contents = @file_get_contents($resolvedPath)) === false) {
            throw new RuntimeException(sprintf('Answers file [%s] cannot be read.', $filePath));
        }

        /** @var mixed $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        $errorMessage = $isOption
            ? 'The --answers-file option must decode to a JSON object.'
            : 'The answers file must decode to a JSON object.';

        throw_unless(is_array($decoded), RuntimeException::class, $errorMessage);

        /** @var array<string, mixed> $decoded */
        return $decoded;
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

        $rawAnswers = $command->hasOption('answers') ? $command->option('answers') : null;
        $answersFile = $command->hasOption('answers-file') ? $command->option('answers-file') : null;
        $preset = $command->hasOption('preset') ? $command->option('preset') : null;

        $hasAnswersSource = false;
        $providedAnswers = [];

        if (is_string($rawAnswers) && mb_trim($rawAnswers) !== '') {
            $hasAnswersSource = true;
            $decoded = json_decode($rawAnswers, true, 512, JSON_THROW_ON_ERROR);
            throw_unless(is_array($decoded), RuntimeException::class, 'The --answers option must decode to a JSON object.');
            $providedAnswers = $decoded;
        } elseif ($answersFile !== null) {
            $hasAnswersSource = true;
            $answersFilePath = is_string($answersFile) ? $answersFile : '';
            $providedAnswers = self::loadAnswersFromFile($answersFilePath, isOption: true);
        } elseif ($preset !== null) {
            $hasAnswersSource = true;
            $presetName = is_string($preset) ? $preset : '';
            $resolvedPreset = Preset::fromName($presetName);
            DependencyValidator::validate($resolvedPreset->answers());
            $providedAnswers = $resolvedPreset->answers();
        } elseif (($envAnswers = self::environmentAnswers()) !== null) {
            $hasAnswersSource = true;
            $decoded = json_decode($envAnswers, true, 512, JSON_THROW_ON_ERROR);
            throw_unless(is_array($decoded), RuntimeException::class, 'The --answers option must decode to a JSON object.');
            $providedAnswers = $decoded;
        } elseif (($envAnswersFile = self::environmentAnswersFile()) !== null) {
            $hasAnswersSource = true;
            $providedAnswers = self::loadAnswersFromFile($envAnswersFile, isOption: false);
        } elseif (($envPreset = self::environmentPreset()) !== null) {
            $hasAnswersSource = true;
            $resolvedPreset = Preset::fromName($envPreset);
            DependencyValidator::validate($resolvedPreset->answers());
            $providedAnswers = $resolvedPreset->answers();
        }

        $savedState = null;
        if (! $hasAnswersSource && InstallerState::exists(base_path())) {
            $savedState = InstallerState::load(base_path());
            if ($savedState instanceof InstallerState) {
                $hasAnswersSource = true;
                $providedAnswers = $savedState->answers;
            }
        }

        if (isset($providedAnswers['ai_tooling']) && ! isset($providedAnswers['ai_guidelines'])) {
            $providedAnswers['ai_guidelines'] = $providedAnswers['ai_tooling'];
        }

        if (isset($providedAnswers['ai_guidelines']) && is_bool($providedAnswers['ai_guidelines'])) {
            $providedAnswers['ai_guidelines'] = $providedAnswers['ai_guidelines'] ? ['guidelines'] : [];
        }

        $isNonInteractive = $hasAnswersSource || ! $isInteractive;
        $isInteractivePrompting = $isInteractive && ! $isNonInteractive;

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
            ->interactive($isInteractivePrompting)
            ->withAnswers($providedAnswers);

        $answers = $pendingAnswers->toArray();

        $rawModules = (array) ($answers['optional_modules'] ?? []);
        $selectedModules = array_values(array_filter($rawModules, is_string(...)));

        $rawAuth = (array) ($answers['auth_features'] ?? []);
        $selectedAuthFeatures = array_values(array_filter($rawAuth, is_string(...)));

        $hasAuthorization = in_array('authorization', $selectedModules, true);

        $rawAi = $providedAnswers['ai_guidelines'] ?? ($answers['ai_guidelines'] ?? null);
        $includeAiGuidelines = match (true) {
            is_bool($rawAi) => $rawAi,
            is_array($rawAi) => in_array('guidelines', $rawAi, true) || in_array('ai-guidelines', $rawAi, true),
            default => true,
        };

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

        if ($savedState instanceof InstallerState) {
            $adminName ??= (isset($savedState->metadata['admin_name']) && is_string($savedState->metadata['admin_name']) ? $savedState->metadata['admin_name'] : null);
            $adminEmail ??= (isset($savedState->metadata['admin_email']) && is_string($savedState->metadata['admin_email']) ? $savedState->metadata['admin_email'] : null);
        }

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
            includeAiGuidelines: $includeAiGuidelines,
        );
    }
}
