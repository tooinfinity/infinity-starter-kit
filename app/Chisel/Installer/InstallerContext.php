<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;

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
        public ?string $adminPassword,
        public bool $isNonInteractive,
        public bool $skipNode,
        public bool $isMockedScript,
    ) {}

    public function redact(string $message): string
    {
        $secrets = [];

        if ($this->adminPassword !== null && $this->adminPassword !== '') {
            $secrets[] = $this->adminPassword;
        }

        $envPassword = Env::get('CHISEL_ADMIN_PASSWORD', Request::server('CHISEL_ADMIN_PASSWORD') ?? getenv('CHISEL_ADMIN_PASSWORD'));
        if (is_string($envPassword) && $envPassword !== '') {
            $secrets[] = $envPassword;
        }

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
