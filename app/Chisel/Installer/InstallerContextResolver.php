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
 * Resolves interactive and non-interactive inputs into an immutable InstallerContext.
 */
final class InstallerContextResolver
{
    /**
     * Determines whether installer hooks should be deferred based on flags and answers.
     */
    public static function shouldDeferInstallerHooks(Command $command): bool
    {
        $hasAnswers = $command->hasOption('answers') && $command->option('answers') !== null;
        if ($hasAnswers) {
            return false;
        }

        return self::installerFlag('LARAVEL_INSTALLER_DEFER_HOOKS');
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
     * @throws RuntimeException
     */
    public static function resolve(Command $command, Script $script, bool $isMockedScript, bool $isInteractive = true): InstallerContext
    {
        /** @var array<string, mixed> $paths */
        $paths = require base_path('chisel-paths.php');

        $rawAnswers = $command->hasOption('answers') ? $command->option('answers') : null;
        $hasAnswersOption = is_string($rawAnswers) && mb_trim($rawAnswers) !== '';
        $hasAnswersSource = false;
        $providedAnswers = [];

        if ($hasAnswersOption) {
            $hasAnswersSource = true;
            $decoded = json_decode($rawAnswers, true, 512, JSON_THROW_ON_ERROR);
            throw_unless(is_array($decoded), RuntimeException::class, 'The --answers option must decode to a JSON object.');

            throw_if(self::containsPasswordKey($decoded), RuntimeException::class, 'Administrator passwords cannot be supplied via --answers for security reasons.');

            $providedAnswers = $decoded;
        } elseif ($rawAnswers !== null) {
            throw new RuntimeException('The --answers option must decode to a JSON object.');
        }

        $savedState = null;
        if (! $hasAnswersSource && InstallerState::exists(base_path())) {
            $savedState = InstallerState::load(base_path());
            if ($savedState instanceof InstallerState) {
                $hasAnswersSource = true;
                $providedAnswers = $savedState->answers;
            }
        }

        $isNoInteraction = $command->hasOption('no-interaction') && (bool) $command->option('no-interaction');
        $isNonInteractive = $hasAnswersOption || ! $isInteractive || $isNoInteraction;

        throw_if($isNonInteractive && ! $hasAnswersSource, RuntimeException::class, 'The --answers option is required when running non-interactively.');

        if ($isNonInteractive && ! $savedState instanceof InstallerState) {
            throw_unless(array_key_exists('auth_features', $providedAnswers), RuntimeException::class, "The 'auth_features' key is required in --answers in non-interactive mode.");

            throw_unless(is_array($providedAnswers['auth_features']), RuntimeException::class, "The 'auth_features' option must be an array.");

            throw_unless(array_key_exists('optional_modules', $providedAnswers), RuntimeException::class, "The 'optional_modules' key is required in --answers in non-interactive mode.");

            throw_unless(is_array($providedAnswers['optional_modules']), RuntimeException::class, "The 'optional_modules' option must be an array.");
            $adminEmailOption = $command->hasOption('admin-email') ? $command->option('admin-email') : null;
            $adminNameOption = $command->hasOption('admin-name') ? $command->option('admin-name') : null;
            $hasAdminInAnswers = isset($providedAnswers['admin']) || isset($providedAnswers['admin_email']) || isset($providedAnswers['admin_name']);
            throw_if($adminEmailOption !== null || $adminNameOption !== null || $hasAdminInAnswers, RuntimeException::class, 'Administrator account creation requires secure password provisioning, which cannot be supplied non-interactively. Run "php artisan authorization:create-admin --stdin" after installation to provision an administrator.');
        }

        if (isset($providedAnswers['ai_tooling']) && ! isset($providedAnswers['ai_guidelines'])) {
            $providedAnswers['ai_guidelines'] = $providedAnswers['ai_tooling'];
        }

        if (isset($providedAnswers['ai_guidelines']) && is_bool($providedAnswers['ai_guidelines'])) {
            $providedAnswers['ai_guidelines'] = $providedAnswers['ai_guidelines'] ? ['guidelines'] : [];
        }

        $isInteractivePrompting = $isInteractive && ! $isNoInteraction && ! $hasAnswersSource;

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

        $adminName = $command->hasOption('admin-name') ? $command->option('admin-name') : null;
        $adminEmail = $command->hasOption('admin-email') ? $command->option('admin-email') : null;

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
            adminName: is_string($adminName) && mb_trim($adminName) !== '' ? mb_trim($adminName) : null,
            adminEmail: is_string($adminEmail) && mb_trim($adminEmail) !== '' ? mb_trim($adminEmail) : null,
            isNonInteractive: $isNonInteractive,
            skipNode: $skipNode,
            isMockedScript: $isMockedScript,
            includeAiGuidelines: $includeAiGuidelines,
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function containsPasswordKey(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && str_contains(mb_strtolower($key), 'password')) {
                return true;
            }

            if (is_array($value) && self::containsPasswordKey($value)) {
                return true;
            }
        }

        return false;
    }
}
