<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use RuntimeException;
use Throwable;

/**
 * Manages the installer checkpoint state file for safe retries and failure diagnosis.
 */
final class InstallerState
{
    public const string STATE_FILE = '.installer-state.json';

    /**
     * @param  array{auth_features: list<string>, optional_modules: list<string>, ai_guidelines?: list<string>}  $answers
     * @param  list<string>  $completedPhases
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $version,
        public string $startedAt,
        public string $updatedAt,
        public string $status,
        public array $answers,
        public array $completedPhases,
        public string $currentPhase,
        public ?string $failedPhase = null,
        public ?string $lastError = null,
        public array $metadata = [],
    ) {}

    public static function statePath(string $basePath): string
    {
        return $basePath.'/'.self::STATE_FILE;
    }

    public static function exists(string $basePath): bool
    {
        return file_exists(self::statePath($basePath));
    }

    public static function load(string $basePath): ?self
    {
        $path = self::statePath($basePath);
        if (! file_exists($path)) {
            return null;
        }

        try {
            $content = (string) file_get_contents($path);
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) {
                return null;
            }

            $rawAnswers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
            $auth = is_array($rawAnswers['auth_features'] ?? null) ? $rawAnswers['auth_features'] : [];
            $modules = is_array($rawAnswers['optional_modules'] ?? null) ? $rawAnswers['optional_modules'] : [];

            $completedPhases = is_array($data['completed_phases'] ?? null) ? $data['completed_phases'] : [];
            $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

            $version = isset($data['version']) && is_int($data['version']) ? $data['version'] : 1;
            $startedAt = isset($data['started_at']) && is_string($data['started_at']) ? $data['started_at'] : date('c');
            $updatedAt = isset($data['updated_at']) && is_string($data['updated_at']) ? $data['updated_at'] : date('c');
            $status = isset($data['status']) && is_string($data['status']) ? $data['status'] : 'in_progress';
            $currentPhase = isset($data['current_phase']) && is_string($data['current_phase']) ? $data['current_phase'] : 'unknown';

            $answers = [
                'auth_features' => array_values(array_filter($auth, is_string(...))),
                'optional_modules' => array_values(array_filter($modules, is_string(...))),
            ];

            if (array_key_exists('ai_guidelines', $rawAnswers) && is_array($rawAnswers['ai_guidelines'])) {
                $answers['ai_guidelines'] = array_values(array_filter($rawAnswers['ai_guidelines'], is_string(...)));
            }

            return new self(
                version: $version,
                startedAt: $startedAt,
                updatedAt: $updatedAt,
                status: $status,
                answers: $answers,
                completedPhases: array_values(array_filter($completedPhases, is_string(...))),
                currentPhase: $currentPhase,
                failedPhase: isset($data['failed_phase']) && is_string($data['failed_phase']) ? $data['failed_phase'] : null,
                lastError: isset($data['last_error']) && is_string($data['last_error']) ? $data['last_error'] : null,
                metadata: self::sanitizeMetadata($metadata),
            );
        } catch (Throwable) {
            return null;
        }
    }

    public static function initialize(string $basePath, InstallerContext $context): self
    {
        $now = date('c');

        $safeAnswers = self::sanitizeAnswers($context->answers);

        $metadata = [];
        if ($context->adminName !== null) {
            $metadata['admin_name'] = $context->adminName;
        }

        if ($context->adminEmail !== null) {
            $metadata['admin_email'] = $context->adminEmail;
        }

        $state = new self(
            version: 1,
            startedAt: $now,
            updatedAt: $now,
            status: 'in_progress',
            answers: $safeAnswers,
            completedPhases: [],
            currentPhase: 'preflight',
            metadata: $metadata,
        );

        $state->save($basePath);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{auth_features: list<string>, optional_modules: list<string>, ai_guidelines?: list<string>}
     */
    public static function sanitizeAnswers(array $answers): array
    {
        $rawAuth = (array) ($answers['auth_features'] ?? []);
        $auth = array_values(array_filter($rawAuth, is_string(...)));
        sort($auth);

        $rawModules = (array) ($answers['optional_modules'] ?? []);
        $modules = array_values(array_filter($rawModules, is_string(...)));
        sort($modules);

        $sanitized = [
            'auth_features' => $auth,
            'optional_modules' => $modules,
        ];

        if (array_key_exists('ai_guidelines', $answers)) {
            $rawGuidelines = (array) $answers['ai_guidelines'];
            $guidelines = array_values(array_filter($rawGuidelines, is_string(...)));
            sort($guidelines);
            $sanitized['ai_guidelines'] = $guidelines;
        }

        return $sanitized;
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];
        foreach ($metadata as $key => $value) {
            $keyString = (string) $key;
            // Never persist password or secret keys
            if (str_contains(mb_strtolower($keyString), 'password') || str_contains(mb_strtolower($keyString), 'secret') || str_contains(mb_strtolower($keyString), 'token')) {
                continue;
            }

            if (is_array($value)) {
                $sanitized[$keyString] = self::sanitizeMetadata($value);
            } elseif (is_scalar($value) || $value === null) {
                $sanitized[$keyString] = $value;
            }
        }

        return $sanitized;
    }

    public function markPhaseComplete(string $basePath, string $phase): void
    {
        if (! in_array($phase, $this->completedPhases, true)) {
            $this->completedPhases[] = $phase;
        }

        $this->status = 'in_progress';
        $this->updatedAt = date('c');
        $this->save($basePath);
    }

    public function setCurrentPhase(string $basePath, string $phase): void
    {
        $this->currentPhase = $phase;
        $this->updatedAt = date('c');
        $this->save($basePath);
    }

    public function recordFailure(string $basePath, string $phase, string $errorMessage): void
    {
        $this->status = 'failed';
        $this->failedPhase = $phase;
        $this->currentPhase = $phase;
        $this->lastError = $this->redactErrorMessage($errorMessage);
        $this->updatedAt = date('c');
        $this->save($basePath);
    }

    public function hasCompleted(string $phase): bool
    {
        return in_array($phase, $this->completedPhases, true);
    }

    public function save(string $basePath): void
    {
        $data = [
            'version' => $this->version,
            'started_at' => $this->startedAt,
            'updated_at' => $this->updatedAt,
            'status' => $this->status,
            'current_phase' => $this->currentPhase,
            'completed_phases' => $this->completedPhases,
            'failed_phase' => $this->failedPhase,
            'last_error' => $this->lastError,
            'answers' => $this->answers,
            'metadata' => self::sanitizeMetadata($this->metadata),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = self::statePath($basePath);
        $tmpPath = $path.'.tmp.'.bin2hex(random_bytes(16));

        $written = @file_put_contents($tmpPath, $json);
        if ($written === false) {
            throw new RuntimeException(sprintf('Failed to write temporary installer state to [%s].', $tmpPath));
        }

        if (! @rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new RuntimeException(sprintf('Failed to atomically rename installer state from [%s] to [%s].', $tmpPath, $path));
        }
    }

    public function delete(string $basePath): bool
    {
        $path = self::statePath($basePath);
        if (file_exists($path)) {
            return @unlink($path);
        }

        return true;
    }

    public function answersConflict(InstallerContext $context): bool
    {
        $savedAuth = $this->answers['auth_features'];
        $currentAuth = $context->selectedAuthFeatures;
        sort($savedAuth);
        sort($currentAuth);

        $savedModules = $this->answers['optional_modules'];
        $currentModules = $context->selectedModules;
        sort($savedModules);
        sort($currentModules);

        if ($savedAuth !== $currentAuth || $savedModules !== $currentModules) {
            return true;
        }

        if (array_key_exists('ai_guidelines', $this->answers) && array_key_exists('ai_guidelines', $context->answers)) {
            $savedGuidelines = (array) $this->answers['ai_guidelines'];
            $currentGuidelines = (array) $context->answers['ai_guidelines'];
            sort($savedGuidelines);
            sort($currentGuidelines);

            return $savedGuidelines !== $currentGuidelines;
        }

        return false;
    }

    public function formatConflictMessage(InstallerContext $context): string
    {
        $savedAuth = $this->answers['auth_features'];
        $currentAuth = $context->selectedAuthFeatures;
        $savedModules = $this->answers['optional_modules'];
        $currentModules = $context->selectedModules;

        $msg = "Conflicting answers on installer retry.\n"
            ."The project was already partially pruned with:\n"
            .'  Authentication: '.($savedAuth !== [] ? implode(', ', $savedAuth) : '(none)')."\n"
            .'  Optional modules: '.($savedModules !== [] ? implode(', ', $savedModules) : '(none)')."\n";

        if (isset($this->answers['ai_guidelines'])) {
            $msg .= '  AI guidelines: '.($this->answers['ai_guidelines'] !== [] ? implode(', ', $this->answers['ai_guidelines']) : '(none)')."\n";
        }

        $msg .= "\nAttempted retry with:\n"
            .'  Authentication: '.($currentAuth !== [] ? implode(', ', $currentAuth) : '(none)')."\n"
            .'  Optional modules: '.($currentModules !== [] ? implode(', ', $currentModules) : '(none)')."\n";

        if (isset($context->answers['ai_guidelines']) && is_array($context->answers['ai_guidelines'])) {
            $contextGuidelines = array_values(array_filter($context->answers['ai_guidelines'], is_string(...)));
            $msg .= '  AI guidelines: '.($contextGuidelines !== [] ? implode(', ', $contextGuidelines) : '(none)')."\n";
        }

        $msg .= "\nThe file structure has already been pruned according to the original selection and cannot be safely changed.\n"
            ."To retry with the previous answers, run:\n"
            ."  php artisan install:features\n"
            ."If administrator setup was requested, provide a new password with --admin-password or CHISEL_ADMIN_PASSWORD; passwords are not stored in the checkpoint.\n"
            .'To start over with different features, delete this directory and run composer create-project again.';

        return $msg;
    }

    private function redactErrorMessage(string $message): string
    {
        $redacted = preg_replace('/(--password[= ])[^\s]+/i', '$1[REDACTED]', $message) ?? $message;

        return preg_replace('/([\'"]password[\'"]\s*=>\s*[\'"])[^\'"]+([\'"])/i', '$1[REDACTED]$2', $redacted) ?? $redacted;
    }
}
