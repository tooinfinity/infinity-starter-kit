<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureRegistry;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Manages the non-secret starter kit configuration manifest recorded after successful installation.
 */
final readonly class GeneratedApplicationManifest
{
    public const int SCHEMA_VERSION = 1;

    public const string MANIFEST_FILE = 'starter-kit-manifest.json';

    /**
     * @param  array{
     *     name: string,
     *     version: ?string,
     *     source_revision: ?string,
     * }  $starterKit
     * @param  array{
     *     explicit: array{
     *         auth_features: list<string>,
     *         optional_modules: list<string>,
     *         ai_guidelines?: list<string>|bool,
     *     },
     *     dependency_derived: list<string>,
     *     all_selected: list<string>,
     *     unselected: list<string>,
     * }  $features
     * @param  list<string>  $authCapabilities
     */
    public function __construct(
        public int $schemaVersion,
        public array $starterKit,
        public ?string $preset,
        public array $features,
        public array $authCapabilities,
        public string $generatedAt,
    ) {}

    public static function manifestPath(string $basePath): string
    {
        $override = Env::get('CHISEL_MANIFEST_PATH', Request::server('CHISEL_MANIFEST_PATH') ?? getenv('CHISEL_MANIFEST_PATH'));
        if (is_string($override) && mb_trim($override) !== '') {
            return $override;
        }

        return $basePath.'/'.self::MANIFEST_FILE;
    }

    public static function exists(string $basePath): bool
    {
        return file_exists(self::manifestPath($basePath));
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws JsonException
     */
    public static function read(string $basePath): ?array
    {
        $path = self::manifestPath($basePath);
        if (! file_exists($path)) {
            return null;
        }

        try {
            $contents = (string) file_get_contents($path);
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($decoded)) {
                return null;
            }

            $result = [];
            foreach ($decoded as $key => $value) {
                if (! is_string($key)) {
                    return null;
                }

                $result[$key] = $value;
            }

            return $result;
        } catch (Throwable) {
            return null;
        }
    }

    public static function fromContext(InstallerContext $context, string $basePath): self
    {
        $allFeatures = FeatureRegistry::all();

        $rawAuth = $context->selectedAuthFeatures;
        $selectedAuth = array_values(array_filter($rawAuth, is_string(...)));
        sort($selectedAuth);

        $rawModules = $context->selectedModules;
        $selectedModules = array_values(array_filter($rawModules, is_string(...)));
        sort($selectedModules);

        $allSelected = array_values(array_unique(array_merge($selectedAuth, $selectedModules)));
        sort($allSelected);

        $unselected = array_values(array_diff(array_keys($allFeatures), $allSelected));
        sort($unselected);

        $dependencyDerived = [];
        foreach ($allSelected as $featureKey) {
            if (! isset($allFeatures[$featureKey])) {
                continue;
            }

            $definition = $allFeatures[$featureKey];
            foreach ($definition->dependencies as $dep) {
                if (in_array($dep, $allSelected, true)) {
                    $dependencyDerived[] = $dep;
                }
            }
        }

        $dependencyDerived = array_values(array_unique($dependencyDerived));
        sort($dependencyDerived);

        $preset = $context->preset;
        if ($preset === null) {
            foreach (Preset::names() as $name) {
                $presetAnswers = Preset::answersFor($name);
                $presetAuth = $presetAnswers['auth_features'];
                $presetModules = $presetAnswers['optional_modules'];
                sort($presetAuth);
                sort($presetModules);

                if ($presetAuth === $selectedAuth && $presetModules === $selectedModules) {
                    $preset = $name;
                    break;
                }
            }
        }

        $starterKitName = 'tooinfinity/infinity-starter-kit';
        $starterKitVersion = null;
        $composerPath = $basePath.'/composer.json';
        if (file_exists($composerPath)) {
            try {
                $composerData = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($composerData)) {
                    if (isset($composerData['name']) && is_string($composerData['name'])) {
                        $starterKitName = $composerData['name'];
                    }

                    if (isset($composerData['version']) && is_string($composerData['version'])) {
                        $starterKitVersion = $composerData['version'];
                    }
                }
            } catch (Throwable) {
            }
        }

        $envVersion = Env::get('STARTER_KIT_VERSION', Request::server('STARTER_KIT_VERSION') ?? getenv('STARTER_KIT_VERSION'));
        if (is_string($envVersion) && mb_trim($envVersion) !== '') {
            $starterKitVersion = mb_trim($envVersion);
        }

        $sourceRevision = self::resolveSourceRevision($basePath);
        $generatedAt = self::resolveTimestamp();

        $explicitFeatures = [
            'auth_features' => $selectedAuth,
            'optional_modules' => $selectedModules,
        ];

        if (array_key_exists('ai_guidelines', $context->answers)) {
            $rawGuidelines = $context->answers['ai_guidelines'];
            if (is_array($rawGuidelines)) {
                $guidelines = array_values(array_filter($rawGuidelines, is_string(...)));
                sort($guidelines);
                $explicitFeatures['ai_guidelines'] = $guidelines;
            } elseif (is_bool($rawGuidelines)) {
                $explicitFeatures['ai_guidelines'] = $rawGuidelines ? ['guidelines'] : [];
            }
        }

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            starterKit: [
                'name' => $starterKitName,
                'version' => $starterKitVersion,
                'source_revision' => $sourceRevision,
            ],
            preset: $preset,
            features: [
                'explicit' => $explicitFeatures,
                'dependency_derived' => $dependencyDerived,
                'all_selected' => $allSelected,
                'unselected' => $unselected,
            ],
            authCapabilities: $selectedAuth,
            generatedAt: $generatedAt,
        );
    }

    public static function resolveSourceRevision(string $basePath): ?string
    {
        $envRevision = Env::get('STARTER_KIT_REVISION', Request::server('STARTER_KIT_REVISION') ?? getenv('STARTER_KIT_REVISION'));
        if (is_string($envRevision) && mb_trim($envRevision) !== '') {
            return mb_trim($envRevision);
        }

        $envSourceRev = Env::get('STARTER_KIT_SOURCE_REVISION', Request::server('STARTER_KIT_SOURCE_REVISION') ?? getenv('STARTER_KIT_SOURCE_REVISION'));
        if (is_string($envSourceRev) && mb_trim($envSourceRev) !== '') {
            return mb_trim($envSourceRev);
        }

        if (! file_exists($basePath.'/.git')) {
            return null;
        }

        $process = new Process(['git', 'rev-parse', 'HEAD'], $basePath);
        $process->run();
        if ($process->isSuccessful()) {
            $output = mb_trim($process->getOutput());
            if ($output !== '') {
                return $output;
            }
        }

        return null;
    }

    public static function resolveTimestamp(): string
    {
        $explicit = Env::get('CHISEL_MANIFEST_TIMESTAMP', Request::server('CHISEL_MANIFEST_TIMESTAMP') ?? getenv('CHISEL_MANIFEST_TIMESTAMP'));
        if (is_string($explicit) && mb_trim($explicit) !== '') {
            return mb_trim($explicit);
        }

        $sourceDateEpoch = Env::get('SOURCE_DATE_EPOCH', Request::server('SOURCE_DATE_EPOCH') ?? getenv('SOURCE_DATE_EPOCH'));
        if (is_numeric($sourceDateEpoch)) {
            return gmdate('Y-m-d\TH:i:s\Z', (int) $sourceDateEpoch);
        }

        if (InstallerContextResolver::installerFlag('CHISEL_DETERMINISTIC_MANIFEST')) {
            return '1970-01-01T00:00:00Z';
        }

        return gmdate('Y-m-d\TH:i:s\Z');
    }

    /**
     * @param  array<array-key, mixed>  $data
     *
     * @throws RuntimeException
     */
    public static function assertNoSensitiveData(array $data): void
    {
        $sensitiveKeywords = [
            'password',
            'secret',
            'token',
            'credential',
            'private_key',
            'api_key',
            'auth_token',
            'bearer',
            'connection_string',
        ];

        foreach ($data as $key => $value) {
            $keyString = mb_strtolower((string) $key);
            foreach ($sensitiveKeywords as $keyword) {
                if (str_contains($keyString, $keyword)) {
                    throw new RuntimeException(sprintf('Sensitive field [%s] cannot be serialized into the starter kit manifest.', (string) $key));
                }
            }

            if (is_array($value)) {
                self::assertNoSensitiveData($value);
            } elseif (is_string($value)) {
                throw_if(preg_match('#^[a-z]+://[^:]+:[^@]+@#i', $value) === 1, RuntimeException::class, 'Connection string with credentials cannot be serialized into the starter kit manifest.');
            }
        }
    }

    /**
     * @throws JsonException
     * @throws RuntimeException
     */
    public static function write(string $basePath, InstallerContext $context): string
    {
        return self::fromContext($context, $basePath)->save($basePath);
    }

    /**
     * @throws JsonException
     * @throws RuntimeException
     */
    public function save(string $basePath): string
    {
        $json = $this->toJson();
        $path = self::manifestPath($basePath);
        $tmpPath = $path.'.tmp.'.bin2hex(random_bytes(16));

        $written = @file_put_contents($tmpPath, $json);
        if ($written === false) {
            throw new RuntimeException(sprintf('Failed to write temporary starter kit manifest to [%s].', $tmpPath));
        }

        if (! @rename($tmpPath, $path)) {
            File::delete($tmpPath);
            throw new RuntimeException(sprintf('Failed to atomically rename starter kit manifest from [%s] to [%s].', $tmpPath, $path));
        }

        return $path;
    }

    /**
     * @return array{
     *     schema_version: int,
     *     starter_kit: array{name: string, version: ?string, source_revision: ?string},
     *     preset: ?string,
     *     features: array{
     *         explicit: array{auth_features: list<string>, optional_modules: list<string>, ai_guidelines?: list<string>|bool},
     *         dependency_derived: list<string>,
     *         all_selected: list<string>,
     *         unselected: list<string>,
     *     },
     *     auth_capabilities: list<string>,
     *     generated_at: string,
     * }
     */
    public function toArray(): array
    {
        $data = [
            'schema_version' => $this->schemaVersion,
            'starter_kit' => $this->starterKit,
            'preset' => $this->preset,
            'features' => $this->features,
            'auth_capabilities' => $this->authCapabilities,
            'generated_at' => $this->generatedAt,
        ];

        self::assertNoSensitiveData($data);

        return $data;
    }

    /**
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
