<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Chisel\Question;
use RuntimeException;

use function Laravel\Prompts\multiselect;

#[Signature('install:features {--answers= : JSON answers for non-interactive installation}')]
#[Description('Select the Infinity starter kit features to retain')]
final class InstallFeaturesCommand extends Command
{
    public function handle(): int
    {
        /** @var \Laravel\Chisel\Script $script */
        $script = require base_path('chisel.php');

        $providedAnswers = $this->option('answers') === null
            ? []
            : json_decode((string) $this->option('answers'), true, 512, JSON_THROW_ON_ERROR);

        $answers = $script
            ->collectAnswers()
            ->onQuestion(fn (Question $question): array => match ($question->type) {
                'multiselect' => multiselect(
                    label: $question->label,
                    options: $question->options,
                    default: $question->default ?? [],
                    required: $question->required,
                    hint: $question->hint,
                ),
                default => throw new RuntimeException("Unsupported question type [{$question->type}]."),
            })
            ->interactive($this->input->isInteractive())
            ->withAnswers($providedAnswers);

        $script->chisel($answers);

        return self::SUCCESS;
    }
}
