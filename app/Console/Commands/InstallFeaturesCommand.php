<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\multiselect;

#[Signature('install:features {--answers= : JSON answers for non-interactive installation}')]
#[Description('Select the Infinity starter kit features to retain')]
final class InstallFeaturesCommand extends Command
{
    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(): int
    {
        /** @var Script $script */
        $script = require base_path('chisel.php');

        $providedAnswers = $this->option('answers') === null
            ? []
            : json_decode((string) $this->option('answers'), true, 512, JSON_THROW_ON_ERROR);

        throw_unless(is_array($providedAnswers), RuntimeException::class, 'The --answers option must decode to a JSON object.');

        /** @var array<string, mixed> $providedAnswers */
        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (Question $question): array => match ($question->type) {
                // @phpstan-ignore match.alwaysTrue (only `multiselect` exists in chisel v0.1; kept for forward compat)
                'multiselect' => multiselect(
                    label: $question->label,
                    options: $question->options,
                    default: $question->default ?? [],
                    required: $question->required,
                    hint: $question->hint,
                ),
                default => throw new RuntimeException(sprintf('Unsupported question type [%s].', $question->type)),
            })
            ->interactive($this->input->isInteractive())
            ->withAnswers($providedAnswers);

        $script->chisel($answers);

        return self::SUCCESS;
    }
}
