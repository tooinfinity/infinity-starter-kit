<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

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
}
