<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Support\Env;

/**
 * Strongly-typed immutable context passed across installer lifecycle stages.
 */
final readonly class InstallerContext
{
    /**
     * @param  array<string, mixed>  $providedAnswers
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $paths
     * @param  list<string>  $selectedModules
     * @param  list<string>  $selectedAuthFeatures
     */
    public function __construct(
        public array $providedAnswers,
        public array $answers,
        public array $paths,
        public array $selectedModules,
        public array $selectedAuthFeatures,
        public bool $hasAuthorization,
        public ?string $adminName,
        public ?string $adminEmail,
        public bool $isNonInteractive,
        public bool $skipNode,
        public bool $isMockedScript,
        public bool $includeAiGuidelines = true,
    ) {}

    public function redact(string $message): string
    {
        $secrets = array_filter([
            Env::get('DB_PASSWORD'),
            getenv('DB_PASSWORD'),
            is_string(config('database.default')) ? config('database.connections.'.config('database.default').'.password') : null,
        ], fn (mixed $val): bool => is_string($val) && $val !== '');

        if (is_array($this->providedAnswers['admin'] ?? null) && is_string($this->providedAnswers['admin']['password'] ?? null) && $this->providedAnswers['admin']['password'] !== '') {
            $secrets[] = $this->providedAnswers['admin']['password'];
        }

        if (is_string($this->providedAnswers['admin_password'] ?? null) && $this->providedAnswers['admin_password'] !== '') {
            $secrets[] = $this->providedAnswers['admin_password'];
        }

        foreach ($secrets as $secret) {
            $message = str_replace($secret, '[REDACTED]', $message);
        }

        return $message;
    }
}
