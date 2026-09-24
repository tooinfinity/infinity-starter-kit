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
        {--answers= : JSON string of answers to skip interactive prompts}')]
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

        if (! file_exists(base_path('chisel.php'))) {
            return self::SUCCESS;
        }

        /** @var Script $script */
        $script = app()->bound(Script::class) ? resolve(Script::class) : require base_path('chisel.php');

        $providedAnswers = $this->option('answers') === null
            ? []
            : json_decode((string) $this->option('answers'), true, 512, JSON_THROW_ON_ERROR);

        throw_unless(is_array($providedAnswers), RuntimeException::class, 'The --answers option must decode to a JSON object.');

        /** @var array<string, mixed> $providedAnswers */
        $answers = $script
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

        $skipNode = $this->shouldSkipNode();

        if (! $skipNode) {
            $this->installFrontendDependencies();
        }

        $script->chisel($answers);

        if (! $skipNode) {
            $this->buildAssets();
        }

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
}
