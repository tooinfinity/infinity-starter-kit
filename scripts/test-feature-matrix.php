<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\GeneratedProjectFixture;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/**
 * @return array{reg: bool, verify: bool, twoFactor: bool, rbac: bool, name: string, answers: array<string, mixed>}
 */
function resolveVariant(int $index): array
{
    $hasReg = ($index & 1) !== 0;
    $hasVerify = ($index & 2) !== 0;
    $has2fa = ($index & 4) !== 0;
    $hasRbac = ($index & 8) !== 0;

    $authFeatures = ['login', 'logout', 'password-reset', 'password-update'];
    if ($hasReg) {
        $authFeatures[] = 'registration';
    }
    if ($hasVerify) {
        $authFeatures[] = 'email-verification';
    }
    if ($has2fa) {
        $authFeatures[] = 'two-factor';
    }

    $optionalModules = $hasRbac ? ['authorization'] : [];

    $name = sprintf(
        'Variant %02d [reg: %s | verify: %s | 2fa: %s | rbac: %s]',
        $index,
        $hasReg ? '+' : '-',
        $hasVerify ? '+' : '-',
        $has2fa ? '+' : '-',
        $hasRbac ? '+' : '-',
    );

    return [
        'reg' => $hasReg,
        'verify' => $hasVerify,
        'twoFactor' => $has2fa,
        'rbac' => $hasRbac,
        'name' => $name,
        'answers' => [
            'auth_features' => $authFeatures,
            'optional_modules' => $optionalModules,
            'ai_guidelines' => false,
        ],
    ];
}

/**
 * @param  array{reg: bool, verify: bool, twoFactor: bool, rbac: bool, name: string, answers: array<string, mixed>}  $variant
 */
function testVariant(int $index, array $variant): bool
{
    $startTime = microtime(true);
    echo "\n====================================================================\n";
    echo "  Running {$variant['name']}\n";
    echo "====================================================================\n";

    $tempDir = GeneratedProjectFixture::create();

    try {
        $answersJson = json_encode($variant['answers'], JSON_THROW_ON_ERROR);

        $installCmd = [
            PHP_BINARY,
            'artisan',
            'install:features',
            "--answers={$answersJson}",
            '--no-interaction',
        ];

        if ($variant['rbac']) {
            $installCmd[] = '--admin-name=Matrix Admin';
            $installCmd[] = '--admin-email=matrix-admin@example.com';
            $installCmd[] = '--admin-password=MatrixAdminPass123!';
        }

        echo "-> Pruning features with install:features...\n";
        $installProcess = GeneratedProjectFixture::run($tempDir, $installCmd, [], 300);

        if ($installProcess->getExitCode() !== 0) {
            echo "FAILED: Feature pruning failed:\n";
            echo $installProcess->getOutput()."\n";
            echo $installProcess->getErrorOutput()."\n";

            return false;
        }

        // 1. Verify Pint code formatting
        echo "-> Checking Pint formatting...\n";
        $pintProcess = GeneratedProjectFixture::run($tempDir, ['vendor/bin/pint', '--test', '--parallel'], [], 60);
        if ($pintProcess->getExitCode() !== 0) {
            echo "FAILED: Pint checks failed:\n".$pintProcess->getOutput()."\n";

            return false;
        }

        // 2. Verify PHPStan static analysis
        echo "-> Checking PHPStan static analysis...\n";
        $stanProcess = GeneratedProjectFixture::run($tempDir, ['vendor/bin/phpstan', '--no-progress'], [], 120);
        if ($stanProcess->getExitCode() !== 0) {
            echo "FAILED: PHPStan analysis failed:\n".$stanProcess->getOutput()."\n";

            return false;
        }

        // 3. Verify TypeScript types
        echo "-> Checking TypeScript types...\n";
        $tsProcess = GeneratedProjectFixture::run($tempDir, ['bun', 'run', 'test:types'], [], 60);
        if ($tsProcess->getExitCode() !== 0) {
            echo "FAILED: TypeScript check failed:\n".$tsProcess->getOutput()."\n";

            return false;
        }

        // 4. Verify Pest test suite
        echo "-> Running Pest test suite...\n";
        $pestProcess = GeneratedProjectFixture::run($tempDir, [
            'vendor/bin/pest',
            '--parallel',
            '--exclude-testsuite=Browser',
            '--exclude-group=installer-e2e',
            '--exclude-group=installer-smoke',
        ], ['APP_ENV' => 'testing'], 180);

        if ($pestProcess->getExitCode() !== 0) {
            echo "FAILED: Pest test suite failed:\n".$pestProcess->getOutput()."\n";

            return false;
        }

        // 5. Verify Disabled Feature Invariants
        echo "-> Verifying disabled feature invariants...\n";
        $routeList = GeneratedProjectFixture::run($tempDir, [PHP_BINARY, 'artisan', 'route:list'])->getOutput();

        if (! $variant['reg'] && str_contains($routeList, 'register')) {
            echo "FAILED: /register route remains in route list when registration is pruned.\n";

            return false;
        }

        $userModelContent = (string) file_get_contents($tempDir.'/app/Models/User.php');

        if (! $variant['verify'] && str_contains($userModelContent, 'MustVerifyEmail')) {
            echo "FAILED: User model implements MustVerifyEmail when email verification is pruned.\n";

            return false;
        }

        if (! $variant['twoFactor'] && str_contains($routeList, 'two-factor')) {
            echo "FAILED: Two-factor routes remain in route list when two-factor is pruned.\n";

            return false;
        }

        if (! $variant['rbac']) {
            if (str_contains($userModelContent, 'HasRoles')) {
                echo "FAILED: User model references HasRoles trait when authorization is pruned.\n";

                return false;
            }

            $providerContent = (string) file_get_contents($tempDir.'/app/Providers/AppServiceProvider.php');
            if (str_contains($providerContent, 'Gate::before')) {
                echo "FAILED: Gate::before super-admin bypass remains in AppServiceProvider when authorization is pruned.\n";

                return false;
            }
        }

        $elapsed = round(microtime(true) - $startTime, 1);
        echo "PASS: {$variant['name']} verified in {$elapsed}s\n";

        return true;
    } finally {
        GeneratedProjectFixture::remove($tempDir);
    }
}

// Parse CLI arguments: specific variant index or all 16
$targetVariant = null;
foreach ($argv as $arg) {
    if (is_numeric($arg)) {
        $targetVariant = (int) $arg;
    } elseif (str_starts_with($arg, '--variant=')) {
        $targetVariant = (int) mb_substr($arg, 10);
    }
}

$variantsToRun = $targetVariant !== null ? [$targetVariant] : range(0, 15);
$failures = [];
$totalStart = microtime(true);

echo 'Starting Feature Matrix Test Suite ('.count($variantsToRun)." variant(s))...\n";

foreach ($variantsToRun as $index) {
    if ($index < 0 || $index > 15) {
        fwrite(STDERR, "Error: Invalid variant index [{$index}]. Must be between 0 and 15.\n");
        exit(1);
    }

    $variant = resolveVariant($index);
    $passed = testVariant($index, $variant);

    if (! $passed) {
        $failures[] = $variant['name'];
    }
}

$totalElapsed = round(microtime(true) - $totalStart, 1);
echo "\n====================================================================\n";
echo "  Feature Matrix Summary ({$totalElapsed}s)\n";
echo "====================================================================\n";

if ($failures === []) {
    echo 'ALL '.count($variantsToRun)." VARIANT(S) PASSED!\n";
    exit(0);
}

echo 'FAILED VARIANTS ('.count($failures)."):\n";
foreach ($failures as $failure) {
    echo "  - {$failure}\n";
}

exit(1);
