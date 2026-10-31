<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureRegistry;
use App\Chisel\Installer\Cleanup;
use App\Chisel\Installer\DependencyValidator;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Process\Process;

if (! function_exists('runChiselSandbox')) {
    /**
     * @param  array<string, mixed>  $answers
     * @param  (Closure(string): void)|null  $beforeRun
     * @return array{dir: string, cleanup: Closure(): void}
     */
    function runChiselSandbox(array $answers, ?Closure $beforeRun = null): array
    {
        $tempDir = sys_get_temp_dir().'/chisel_contract_'.uniqid();
        mkdir($tempDir, 0777, true);

        $files = explode("\n", mb_trim((string) shell_exec('git ls-files -c -o --exclude-standard')));
        foreach ($files as $file) {
            if ($file === '' || $file === 'composer.lock' || ! file_exists(base_path($file))) {
                continue;
            }

            $target = $tempDir.'/'.$file;
            @mkdir(dirname($target), 0777, true);
            copy(base_path($file), $target);
        }

        @symlink(base_path('vendor'), $tempDir.'/vendor');

        if ($beforeRun instanceof Closure) {
            $beforeRun($tempDir);
        }

        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'] = 'true';

        try {
            $script = require $tempDir.'/chisel.php';
            $script->chisel($answers);

            /** @var array<string, mixed> $paths */
            $paths = require $tempDir.'/chisel-paths.php';
            chiselCleanup($tempDir, $paths);
        } finally {
            putenv('LARAVEL_INSTALLER_NO_NODE');
            unset($GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'], $GLOBALS['_SERVER']['LARAVEL_INSTALLER_NO_NODE']);
        }

        return [
            'dir' => $tempDir,
            'cleanup' => function () use ($tempDir): void {
                exec('rm -rf '.escapeshellarg($tempDir));
            },
        ];
    }
}

if (! function_exists('assertPhpSyntaxValid')) {
    function assertPhpSyntaxValid(string $directory): void
    {
        $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $syntaxErrors = [];

        foreach ($rdi as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! str_contains($file->getPathname(), '.blade.php')) {
                try {
                    token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
                } catch (ParseError $parseError) {
                    $syntaxErrors[] = $file->getPathname().': '.$parseError->getMessage().' on line '.$parseError->getLine();
                }
            }
        }

        expect($syntaxErrors)->toBeEmpty('Found PHP syntax errors in sandbox: '.implode("\n", $syntaxErrors));
    }
}

if (! function_exists('assertNoOrphanedMarkersInSandbox')) {
    function assertNoOrphanedMarkersInSandbox(string $directory): void
    {
        $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $filesWithMarkers = [];

        foreach ($rdi as $file) {
            if ($file->isFile() && ! str_starts_with($file->getFilename(), '.') && $file->getFilename() !== 'README.md') {
                $content = (string) file_get_contents($file->getPathname());
                if (str_contains($content, '@chisel-') || str_contains($content, '@end-chisel-')) {
                    $filesWithMarkers[] = str_replace($directory.'/', '', $file->getPathname());
                }
            }
        }

        expect($filesWithMarkers)->toBeEmpty('Found unhandled chisel markers in: '.implode(', ', $filesWithMarkers));
    }
}

/**
 * Validates that an optional feature definition fulfills all contract metadata specifications.
 *
 * Throws InvalidArgumentException if any contract rule is violated.
 */
function validateFeatureContract(FeatureDefinition $feature): void
{
    throw_if(mb_trim($feature->key) === '', InvalidArgumentException::class, 'Feature definition must have a non-empty key.');

    if (mb_trim($feature->label) === '') {
        throw new InvalidArgumentException("Feature [{$feature->key}] must have a non-empty label.");
    }

    if ($feature->sectionMarker === null || mb_trim($feature->sectionMarker) === '') {
        throw new InvalidArgumentException("Optional feature [{$feature->key}] must specify a non-empty sectionMarker.");
    }

    if ($feature->exclusiveFiles === []) {
        throw new InvalidArgumentException("Optional feature [{$feature->key}] must declare at least one exclusiveFile.");
    }

    $cleanupFiles = Cleanup::files();

    foreach ($feature->exclusiveFiles as $file) {
        if (! in_array($file, $cleanupFiles, true) && ! file_exists(base_path($file))) {
            throw new InvalidArgumentException("Exclusive file [{$file}] declared for feature [{$feature->key}] does not exist on disk.");
        }
    }

    foreach ($feature->markerFiles as $file) {
        if (! file_exists(base_path($file))) {
            throw new InvalidArgumentException("Marker file [{$file}] declared for feature [{$feature->key}] does not exist on disk.");
        }

        $content = (string) file_get_contents(base_path($file));
        $startTag = '@chisel-'.$feature->sectionMarker;
        $endTag = '@end-chisel-'.$feature->sectionMarker;

        if (! str_contains($content, $startTag) || ! str_contains($content, $endTag)) {
            throw new InvalidArgumentException("Marker file [{$file}] for feature [{$feature->key}] does not contain expected tags [{$startTag}] and [{$endTag}].");
        }
    }

    foreach ($feature->emptyDirectories as $dir) {
        if (! is_dir(base_path($dir))) {
            throw new InvalidArgumentException("Empty directory [{$dir}] declared for feature [{$feature->key}] does not exist on disk.");
        }
    }

    if ($feature->composerPackages !== []) {
        /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $composer */
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($feature->composerPackages as $pkg) {
            if (! isset($composer['require'][$pkg]) && ! isset($composer['require-dev'][$pkg])) {
                throw new InvalidArgumentException("Composer package [{$pkg}] for feature [{$feature->key}] is missing from composer.json.");
            }
        }
    }

    if ($feature->frontendPackages !== []) {
        /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $packageJson */
        $packageJson = json_decode((string) file_get_contents(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($feature->frontendPackages as $pkg) {
            if (! isset($packageJson['dependencies'][$pkg]) && ! isset($packageJson['devDependencies'][$pkg])) {
                throw new InvalidArgumentException("Frontend package [{$pkg}] for feature [{$feature->key}] is missing from package.json.");
            }
        }
    }

    $validModules = array_keys(FeatureRegistry::optionalModules());
    foreach ($feature->dependencies as $dep) {
        if ($dep === $feature->key) {
            throw new InvalidArgumentException("Feature [{$feature->key}] cannot depend on itself.");
        }

        if (! in_array($dep, $validModules, true)) {
            throw new InvalidArgumentException("Feature [{$feature->key}] references unknown dependency module [{$dep}].");
        }
    }
}

/**
 * Extracts declared class/interface/enum names from PHP files declared in exclusiveFiles.
 *
 * @param  list<string>  $exclusiveFiles
 * @return list<array{fqcn: string, short: string}>
 */
function extractDeclaredPhpClasses(array $exclusiveFiles, string $basePath): array
{
    $classes = [];
    foreach ($exclusiveFiles as $file) {
        $fullPath = $basePath.'/'.$file;
        if (! file_exists($fullPath) || ! str_ends_with($file, '.php')) {
            continue;
        }

        $content = (string) file_get_contents($fullPath);
        $tokens = token_get_all($content);
        $namespace = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                $namespace = '';
                $i += 2;
                while ($i < $count && (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) || $tokens[$i] === '\\')) {
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                    $i++;
                }
            }

            if (in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $i += 2;
                if ($i < $count && is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                    $shortName = $tokens[$i][1];
                    $fqcn = $namespace !== '' ? $namespace.'\\'.$shortName : $shortName;
                    $classes[] = [
                        'fqcn' => $fqcn,
                        'short' => $shortName,
                    ];
                }
            }
        }
    }

    return $classes;
}

/**
 * Scans remaining sandbox PHP files for orphaned use statements or usages of classes belonging to a removed feature.
 *
 * @param  list<array{fqcn: string, short: string}>  $declaredClasses
 */
function assertNoOrphanedBackendReferences(string $directory, array $declaredClasses): void
{
    $orphans = [];
    $scanDirs = ['app', 'bootstrap', 'config', 'routes', 'database', 'tests'];

    foreach ($scanDirs as $sub) {
        $path = $directory.'/'.$sub;
        if (! is_dir($path)) {
            continue;
        }

        $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        foreach ($rdi as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $relPath = str_replace($directory.'/', '', $file->getPathname());

            foreach ($declaredClasses as $class) {
                $fqcn = $class['fqcn'];
                $short = $class['short'];

                if (preg_match('/use\s+\\\\?'.preg_quote($fqcn, '/').'\s*;/i', $content)) {
                    $orphans[] = "Orphaned use statement for [{$fqcn}] in [{$relPath}]";

                    continue;
                }

                if (str_contains($content, '\\'.$fqcn)) {
                    $orphans[] = "Orphaned inline FQCN [\\{$fqcn}] in [{$relPath}]";

                    continue;
                }

                if (preg_match('/\bnew\s+\\\\?'.preg_quote($short, '/').'\b/', $content)) {
                    $orphans[] = "Orphaned instantiation of [{$short}] in [{$relPath}]";

                    continue;
                }

                if (preg_match('/\b'.preg_quote($short, '/').'::(?!class\b)[a-zA-Z0-9_]+\b/', $content)) {
                    $orphans[] = "Orphaned static call [{$short}::] in [{$relPath}]";
                }
            }
        }
    }

    expect($orphans)->toBeEmpty('Found orphaned backend references: '.implode("\n", $orphans));
}

/**
 * Scans remaining sandbox TypeScript/JavaScript files for orphaned imports of pruned packages or components.
 */
function assertNoOrphanedFrontendReferences(string $directory, FeatureDefinition $feature): void
{
    $orphans = [];
    $jsDir = $directory.'/resources/js';
    if (! is_dir($jsDir)) {
        return;
    }

    $bannedSpecifiers = $feature->frontendPackages;

    foreach ($feature->exclusiveFiles as $file) {
        if (str_starts_with($file, 'resources/js/')) {
            $rel = mb_substr($file, mb_strlen('resources/js/'));
            $noExt = preg_replace('/\.(tsx?|jsx?|vue)$/', '', $rel);
            $bannedSpecifiers[] = '@/'.$noExt;
        }
    }

    if ($bannedSpecifiers === []) {
        return;
    }

    $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($jsDir));
    foreach ($rdi as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['ts', 'tsx', 'js', 'jsx', 'vue'], true)) {
            continue;
        }

        $content = (string) file_get_contents($file->getPathname());
        $relPath = str_replace($directory.'/', '', $file->getPathname());

        if (preg_match_all('/(?:import|from|require)\s*\(?[\'"]([^\'"]+)[\'"]\)?/', $content, $matches)) {
            foreach ($matches[1] as $specifier) {
                foreach ($bannedSpecifiers as $banned) {
                    if ($specifier === $banned || str_starts_with($specifier, $banned.'/')) {
                        $orphans[] = "Orphaned frontend import [{$specifier}] in [{$relPath}]";
                    }
                }
            }
        }
    }

    expect($orphans)->toBeEmpty('Found orphaned frontend references: '.implode("\n", $orphans));
}

function sandboxAutoloaderScript(): string
{
    return <<<'PHP'
        $originalLoader = require 'vendor/autoload.php';
        $originalLoader->unregister();
        $loader = new Composer\Autoload\ClassLoader();
        foreach ($originalLoader->getPrefixes() as $prefix => $paths) {
            $loader->set($prefix, $paths);
        }
        $loader->set('', $originalLoader->getFallbackDirs());
        foreach ($originalLoader->getPrefixesPsr4() as $prefix => $paths) {
            $loader->setPsr4($prefix, $paths);
        }
        $loader->setPsr4('', $originalLoader->getFallbackDirsPsr4());
        $roots = [
            'App\\' => getcwd().'/app',
            'Database\\Factories\\' => getcwd().'/database/factories',
            'Database\\Seeders\\' => getcwd().'/database/seeders',
        ];
        foreach ($roots as $prefix => $path) {
            $loader->setPsr4($prefix, $path);
        }
        $classMap = array_filter($originalLoader->getClassMap(), static function ($class) use ($roots) {
            foreach ($roots as $prefix => $path) {
                if (str_starts_with($class, $prefix)) {
                    return false;
                }
            }
            return true;
        }, ARRAY_FILTER_USE_KEY);
        $loader->addClassMap($classMap);
        $loader->setUseIncludePath($originalLoader->getUseIncludePath());
        $loader->register(true);
        PHP;
}

/**
 * Boots the Laravel application inside the generated sandbox and asserts zero boot errors.
 */
function assertSandboxBootsCleanly(string $directory): void
{
    $script = sandboxAutoloaderScript().' $app = require "bootstrap/app.php"; $app->make('.Kernel::class.'::class)->bootstrap(); echo "BOOT_SUCCESSFUL";';
    $process = new Process([PHP_BINARY, '-r', $script], $directory, [
        'APP_ENV' => 'testing',
        'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
    ]);

    $process->run();

    expect($process->isSuccessful())->toBeTrue('Sandbox application boot failed: '.$process->getErrorOutput())
        ->and($process->getOutput())->toContain('BOOT_SUCCESSFUL');
}

/**
 * Returns transitive dependencies for a feature.
 *
 * @return list<string>
 */
function resolveDependenciesForFeature(string $featureKey): array
{
    $definitions = FeatureRegistry::optionalModules();
    $resolved = [$featureKey];
    $queue = [$featureKey];

    while ($queue !== []) {
        $current = array_shift($queue);
        if (isset($definitions[$current])) {
            foreach ($definitions[$current]->dependencies as $dep) {
                if (! in_array($dep, $resolved, true)) {
                    $resolved[] = $dep;
                    $queue[] = $dep;
                }
            }
        }
    }

    return $resolved;
}

/**
 * Returns all optional modules that transitively depend on the specified feature.
 *
 * @return list<string>
 */
function getDependentsForFeature(string $featureKey): array
{
    $dependents = [$featureKey];
    $changed = true;

    while ($changed) {
        $changed = false;
        foreach (FeatureRegistry::optionalModules() as $key => $def) {
            if (in_array($key, $dependents, true)) {
                continue;
            }

            foreach ($def->dependencies as $dep) {
                if (in_array($dep, $dependents, true)) {
                    $dependents[] = $key;
                    $changed = true;
                    break;
                }
            }
        }
    }

    return $dependents;
}

dataset('optional_modules', function (): array {
    $modules = [];
    foreach (FeatureRegistry::optionalModules() as $key => $feature) {
        $modules[$key] = [$feature];
    }

    return $modules;
});

// =========================================================================
// Criterion 1 & 2: Contract Metadata Requirements & Failure on Violation
// =========================================================================

test('every registered optional feature satisfies contract metadata requirements', function (FeatureDefinition $feature): void {
    expect(fn () => validateFeatureContract($feature))->not->toThrow(Throwable::class);
})->with('optional_modules');

test('contract validation fails when optional feature has empty key', function (): void {
    $invalid = new FeatureDefinition(
        key: '',
        label: 'Invalid Feature',
        sectionMarker: 'invalid',
        exclusiveFiles: ['app/Models/User.php'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'Feature definition must have a non-empty key.');
});

test('contract validation fails when optional feature is missing sectionMarker', function (): void {
    $invalid = new FeatureDefinition(
        key: 'invalid-marker',
        label: 'Invalid Marker',
        exclusiveFiles: ['app/Models/User.php'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'must specify a non-empty sectionMarker');
});

test('contract validation fails when optional feature has empty exclusiveFiles', function (): void {
    $invalid = new FeatureDefinition(
        key: 'no-files',
        label: 'No Files',
        sectionMarker: 'no-files',
        exclusiveFiles: [],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'must declare at least one exclusiveFile');
});

test('contract validation fails when optional feature references non-existent exclusive file', function (): void {
    $invalid = new FeatureDefinition(
        key: 'missing-file',
        label: 'Missing File',
        sectionMarker: 'missing-file',
        exclusiveFiles: ['app/Models/NonExistentModel12345.php'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'does not exist on disk');
});

test('contract validation fails when optional feature references non-existent marker file', function (): void {
    $invalid = new FeatureDefinition(
        key: 'missing-marker-file',
        label: 'Missing Marker File',
        sectionMarker: 'missing-marker',
        markerFiles: ['app/Models/NonExistentMarkerFile12345.php'],
        exclusiveFiles: ['app/Models/User.php'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'does not exist on disk');
});

test('contract validation fails when marker file does not contain delimiter tags', function (): void {
    $invalid = new FeatureDefinition(
        key: 'untagged-marker-file',
        label: 'Untagged Marker File',
        sectionMarker: 'non-existent-section-marker-xyz',
        markerFiles: ['app/Models/User.php'],
        exclusiveFiles: ['app/Models/User.php'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'does not contain expected tags');
});

test('contract validation fails when declared composer package is not in composer.json', function (): void {
    $invalid = new FeatureDefinition(
        key: 'bad-composer-pkg',
        label: 'Bad Composer Pkg',
        sectionMarker: 'bad-composer',
        exclusiveFiles: ['app/Models/User.php'],
        composerPackages: ['vendor/non-existent-composer-package-xyz'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'missing from composer.json');
});

test('contract validation fails when declared frontend package is not in package.json', function (): void {
    $invalid = new FeatureDefinition(
        key: 'bad-frontend-pkg',
        label: 'Bad Frontend Pkg',
        sectionMarker: 'bad-frontend',
        exclusiveFiles: ['app/Models/User.php'],
        frontendPackages: ['@vendor/non-existent-frontend-package-xyz'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'missing from package.json');
});

test('contract validation fails when dependency references unknown optional module', function (): void {
    $invalid = new FeatureDefinition(
        key: 'bad-dep',
        label: 'Bad Dependency',
        sectionMarker: 'bad-dep',
        exclusiveFiles: ['app/Models/User.php'],
        dependencies: ['non-existent-module-abc'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'references unknown dependency module');
});

test('contract validation fails when feature declares self-dependency', function (): void {
    $invalid = new FeatureDefinition(
        key: 'self-dep',
        label: 'Self Dependency',
        sectionMarker: 'self-dep',
        exclusiveFiles: ['app/Models/User.php'],
        dependencies: ['self-dep'],
    );

    expect(fn () => validateFeatureContract($invalid))
        ->toThrow(InvalidArgumentException::class, 'cannot depend on itself');
});

// =========================================================================
// Criterion 1, 4 & 6: Installed / Enabled State Contract
// =========================================================================

test('sandbox autoloader uses sandbox classes and cannot load pruned classes from the original classmap', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];
        $script = sandboxAutoloaderScript().<<<'PHP'

            $classes = [
                'App\\Models\\User' => '/app/Models/User.php',
                'Database\\Factories\\UserFactory' => '/database/factories/UserFactory.php',
                'Database\\Seeders\\DatabaseSeeder' => '/database/seeders/DatabaseSeeder.php',
            ];
            foreach ($classes as $class => $path) {
                if (! isset($originalLoader->getClassMap()[$class]) || $loader->findFile($class) !== getcwd().$path) {
                    throw new RuntimeException('Wrong sandbox path for '.$class);
                }
            }
            if (! isset($originalLoader->getClassMap()['App\\Chisel\\FeatureRegistry']) || $loader->findFile('App\\Chisel\\FeatureRegistry') !== false) {
                throw new RuntimeException('Pruned class resolved outside the sandbox');
            }
            PHP;
        $process = new Process([PHP_BINARY, '-r', $script], $dir);
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        file_put_contents($dir.'/app/Providers/AppServiceProvider.php', "<?php\nnamespace App\\Providers;\nthrow new \\RuntimeException('Sandbox provider loaded');\n");
        expect(fn () => assertSandboxBootsCleanly($dir))
            ->toThrow(ExpectationFailedException::class, 'Sandbox provider loaded');
    } finally {
        ($sandbox['cleanup'])();
    }
});

test('every optional feature in installed state retains all exclusive files, packages, and boots cleanly', function (FeatureDefinition $feature): void {
    $selectedModules = resolveDependenciesForFeature($feature->key);

    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => $selectedModules,
    ]);

    try {
        $dir = $sandbox['dir'];
        $cleanupFiles = Cleanup::files();

        foreach ($feature->exclusiveFiles as $file) {
            if (in_array($file, $cleanupFiles, true)) {
                continue;
            }

            $isPrunedByOtherFeature = false;
            foreach (FeatureRegistry::all() as $otherKey => $otherFeature) {
                if ($otherKey !== $feature->key && in_array($file, $otherFeature->exclusiveFiles, true)) {
                    if (! in_array($otherKey, $selectedModules, true)) {
                        $isPrunedByOtherFeature = true;
                        break;
                    }
                }
            }

            if ($isPrunedByOtherFeature) {
                continue;
            }

            expect(file_exists($dir.'/'.$file))
                ->toBeTrue("Expected exclusive file [{$file}] for retained feature [{$feature->key}] to exist in sandbox.");
        }

        if ($feature->composerPackages !== []) {
            /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $composer */
            $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($feature->composerPackages as $pkg) {
                expect(isset($composer['require'][$pkg]) || isset($composer['require-dev'][$pkg]))
                    ->toBeTrue("Expected composer package [{$pkg}] for feature [{$feature->key}] to remain in composer.json.");
            }
        }

        if ($feature->frontendPackages !== []) {
            /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $packageJson */
            $packageJson = json_decode((string) file_get_contents($dir.'/package.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($feature->frontendPackages as $pkg) {
                expect(isset($packageJson['dependencies'][$pkg]) || isset($packageJson['devDependencies'][$pkg]))
                    ->toBeTrue("Expected frontend package [{$pkg}] for feature [{$feature->key}] to remain in package.json.");
            }
        }

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
        assertSandboxBootsCleanly($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
})->with('optional_modules');

test('enabled-feature contract detects accidentally pruned required artifact', function (): void {
    $localization = FeatureRegistry::optionalModules()['localization'];
    $cleanupFiles = Cleanup::files();

    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => ['localization'],
    ]);

    try {
        $dir = $sandbox['dir'];

        // Simulate accidental removal of a required exclusive file
        $targetFile = $dir.'/lang/en/localization.php';
        if (file_exists($targetFile)) {
            unlink($targetFile);
        }

        $detectedPruning = false;
        foreach ($localization->exclusiveFiles as $file) {
            if (! in_array($file, $cleanupFiles, true) && ! file_exists($dir.'/'.$file)) {
                $detectedPruning = true;
                break;
            }
        }

        expect($detectedPruning)->toBeTrue('Expected contract assertion to detect accidentally pruned exclusive file.');
    } finally {
        ($sandbox['cleanup'])();
    }
});

// =========================================================================
// Criterion 1, 3 & 6: Removed / Disabled State Contract & Orphaned Refs Detection
// =========================================================================

test('every optional feature in removed state is completely pruned without orphaned backend or frontend references', function (FeatureDefinition $feature): void {
    $allOptional = array_keys(FeatureRegistry::optionalModules());
    $excluded = getDependentsForFeature($feature->key);
    $selectedModules = array_values(array_diff($allOptional, $excluded));

    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => $selectedModules,
    ]);

    try {
        $dir = $sandbox['dir'];

        foreach ($feature->exclusiveFiles as $file) {
            expect(file_exists($dir.'/'.$file))
                ->toBeFalse("Exclusive file [{$file}] for disabled feature [{$feature->key}] should have been pruned.");
        }

        if ($feature->composerPackages !== []) {
            /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $composer */
            $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($feature->composerPackages as $pkg) {
                expect(isset($composer['require'][$pkg]) || isset($composer['require-dev'][$pkg]))
                    ->toBeFalse("Composer package [{$pkg}] for disabled feature [{$feature->key}] should have been pruned.");
            }
        }

        if ($feature->frontendPackages !== []) {
            /** @var array{dependencies?: array<string, string>, devDependencies?: array<string, string>} $packageJson */
            $packageJson = json_decode((string) file_get_contents($dir.'/package.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($feature->frontendPackages as $pkg) {
                expect(isset($packageJson['dependencies'][$pkg]) || isset($packageJson['devDependencies'][$pkg]))
                    ->toBeFalse("Frontend package [{$pkg}] for disabled feature [{$feature->key}] should have been pruned.");
            }
        }

        $declaredClasses = extractDeclaredPhpClasses($feature->exclusiveFiles, base_path());
        assertNoOrphanedBackendReferences($dir, $declaredClasses);
        assertNoOrphanedFrontendReferences($dir, $feature);

        if ($feature->key === 'notifications') {
            $deleteAccountTest = (string) file_get_contents($dir.'/tests/Feature/Controllers/DeleteAccountControllerTest.php');
            expect($deleteAccountTest)
                ->not->toContain('use Illuminate\\Support\\Facades\\DB;', 'use Illuminate\\Support\\Str;')
                ->toContain('use Illuminate\\Support\\Facades\\Hash;');
        }

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
        assertSandboxBootsCleanly($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
})->with('optional_modules');

test('disabled-feature contract detects injected orphaned backend and frontend references', function (): void {
    $localization = FeatureRegistry::optionalModules()['localization'];

    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => ['authorization', 'settings'],
    ]);

    try {
        $dir = $sandbox['dir'];

        // Inject an orphaned backend use statement into an existing controller
        $backendFile = $dir.'/app/Http/Controllers/HomeController.php';
        file_put_contents($backendFile, "<?php\n\nnamespace App\Http\Controllers;\n\nuse App\Enums\Locale;\n\nclass HomeController {}\n");

        // Inject an orphaned frontend import into an existing component
        $frontendFile = $dir.'/resources/js/components/app-header.tsx';
        file_put_contents($frontendFile, "import { LanguageSelector } from '@/components/language-selector';\nexport function AppHeader() {}\n");

        $declaredClasses = extractDeclaredPhpClasses($localization->exclusiveFiles, base_path());

        expect(fn () => assertNoOrphanedBackendReferences($dir, $declaredClasses))
            ->toThrow(ExpectationFailedException::class);

        expect(fn () => assertNoOrphanedFrontendReferences($dir, $localization))
            ->toThrow(ExpectationFailedException::class);
    } finally {
        ($sandbox['cleanup'])();
    }
});

// =========================================================================
// Criterion 5: Dependency-Driven Combinations
// =========================================================================

test('reporting requires audit-trails, user-management, and authorization in dependency graph', function (): void {
    $reporting = FeatureRegistry::optionalModules()['reporting'];

    expect($reporting->dependencies)->toContain('audit-trails')
        ->and($reporting->dependencies)->toContain('user-management')
        ->and($reporting->dependencies)->toContain('authorization');
});

test('cascading removal prunes full dependent tree when root authorization is omitted', function (): void {
    $allOptional = array_keys(FeatureRegistry::optionalModules());
    $excluded = getDependentsForFeature('authorization');
    $selectedModules = array_values(array_diff($allOptional, $excluded));

    expect($excluded)->toContain('authorization')
        ->and($excluded)->toContain('settings')
        ->and($excluded)->toContain('user-management')
        ->and($excluded)->toContain('audit-trails')
        ->and($excluded)->toContain('reporting')
        ->and($selectedModules)->toEqualCanonicalizing(['localization', 'notifications']);

    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => $selectedModules,
    ]);

    try {
        $dir = $sandbox['dir'];

        // Verify dependent features are pruned
        expect(file_exists($dir.'/app/Enums/Permission.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Models/Setting.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/Reporting/AuditReportController.php'))->toBeFalse();

        // Verify independent features remain intact
        expect(file_exists($dir.'/app/Http/Controllers/LocaleController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/NotificationController.php'))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
        assertSandboxBootsCleanly($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

test('intermediate cascade removes reporting when audit-trails is disabled but retains siblings', function (): void {
    $allOptional = array_keys(FeatureRegistry::optionalModules());
    $excluded = getDependentsForFeature('audit-trails');
    $selectedModules = array_values(array_diff($allOptional, $excluded));

    expect($excluded)->toEqualCanonicalizing(['audit-trails', 'reporting'])
        ->and($selectedModules)->toEqualCanonicalizing([
            'authorization',
            'settings',
            'user-management',
            'localization',
            'notifications',
        ]);

    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => $selectedModules,
    ]);

    try {
        $dir = $sandbox['dir'];

        // Pruned
        expect(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/Reporting/AuditReportController.php'))->toBeFalse();

        // Retained
        expect(file_exists($dir.'/app/Enums/Permission.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/Setting.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LocaleController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/NotificationController.php'))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
        assertSandboxBootsCleanly($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

test('selecting module without required dependencies throws RuntimeException', function (): void {
    expect(fn () => DependencyValidator::validate(['optional_modules' => ['reporting']]))
        ->toThrow(RuntimeException::class, 'requires');
});
