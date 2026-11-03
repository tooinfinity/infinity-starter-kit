<?php

declare(strict_types=1);

namespace Tests\Unit\Chisel;

use App\Chisel\Installer\PerformanceBudget;
use App\Chisel\Installer\Profile;
use InvalidArgumentException;
use RuntimeException;

test('performance budget canonicalProfileName resolves valid profile names case-insensitively with trimming', function (string $input, string $expected): void {
    expect(PerformanceBudget::canonicalProfileName($input))->toBe($expected);
})->with([
    'minimal exact' => ['minimal', Profile::MINIMAL],
    'minimal uppercase' => ['MINIMAL', Profile::MINIMAL],
    'minimal padded' => ['  minimal  ', Profile::MINIMAL],
    'default exact' => ['default', Profile::STANDARD],
    'default uppercase' => ['DEFAULT', Profile::STANDARD],
    'standard exact' => ['standard', Profile::STANDARD],
    'standard mixed' => ['Standard', Profile::STANDARD],
    'full exact' => ['full', Profile::FULL],
    'full uppercase' => ['FULL', Profile::FULL],
]);

test('performance budget canonicalProfileName throws InvalidArgumentException for unknown profile names', function (string $invalid): void {
    expect(fn (): string => PerformanceBudget::canonicalProfileName($invalid))
        ->toThrow(InvalidArgumentException::class, "Unknown profile [{$invalid}] for performance budget resolution.");
})->with([
    'empty' => [''],
    'unknown' => ['unknown'],
    'invalid-profile' => ['custom-preset'],
]);

test('performance budget queryBudgetsFor returns expected canonical query ceilings for all profiles', function (string $profile, array $expectedBudgets): void {
    $budgets = PerformanceBudget::queryBudgetsFor($profile);

    expect($budgets)->toBe($expectedBudgets);
})->with([
    'minimal' => [
        Profile::MINIMAL,
        [
            PerformanceBudget::REQUEST_WELCOME => 0,
            PerformanceBudget::REQUEST_LOGIN => 0,
            PerformanceBudget::REQUEST_DASHBOARD => 0,
        ],
    ],
    'default' => [
        Profile::DEFAULT,
        [
            PerformanceBudget::REQUEST_WELCOME => 1,
            PerformanceBudget::REQUEST_LOGIN => 1,
            PerformanceBudget::REQUEST_DASHBOARD => 4,
        ],
    ],
    'standard' => [
        Profile::STANDARD,
        [
            PerformanceBudget::REQUEST_WELCOME => 1,
            PerformanceBudget::REQUEST_LOGIN => 1,
            PerformanceBudget::REQUEST_DASHBOARD => 4,
        ],
    ],
    'full' => [
        Profile::FULL,
        [
            PerformanceBudget::REQUEST_WELCOME => 1,
            PerformanceBudget::REQUEST_LOGIN => 1,
            PerformanceBudget::REQUEST_DASHBOARD => 4,
        ],
    ],
]);

test('performance budget queryBudget retrieves budget for valid request and throws for unknown request', function (): void {
    expect(PerformanceBudget::queryBudget(Profile::MINIMAL, PerformanceBudget::REQUEST_WELCOME))->toBe(0)
        ->and(PerformanceBudget::queryBudget(Profile::FULL, PerformanceBudget::REQUEST_DASHBOARD))->toBe(4);

    expect(fn (): int => PerformanceBudget::queryBudget(Profile::FULL, 'GET /unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unknown canonical request [GET /unknown].');
});

test('performance budget bundleBudgetsFor returns expected metrics for all profiles', function (string $profile, string $metric, int $expectedBudget): void {
    expect(PerformanceBudget::bundleBudget($profile, $metric))->toBe($expectedBudget);
})->with([
    'minimal entry_js' => [Profile::MINIMAL, PerformanceBudget::METRIC_ENTRY_JS, 95_000],
    'minimal entry_css' => [Profile::MINIMAL, PerformanceBudget::METRIC_ENTRY_CSS, 135_000],
    'minimal total_js' => [Profile::MINIMAL, PerformanceBudget::METRIC_TOTAL_JS, 650_000],
    'minimal total_css' => [Profile::MINIMAL, PerformanceBudget::METRIC_TOTAL_CSS, 140_000],
    'default entry_js' => [Profile::DEFAULT, PerformanceBudget::METRIC_ENTRY_JS, 95_000],
    'default total_js' => [Profile::DEFAULT, PerformanceBudget::METRIC_TOTAL_JS, 850_000],
    'full entry_js' => [Profile::FULL, PerformanceBudget::METRIC_ENTRY_JS, 95_000],
    'full total_js' => [Profile::FULL, PerformanceBudget::METRIC_TOTAL_JS, 950_000],
    'full total_css' => [Profile::FULL, PerformanceBudget::METRIC_TOTAL_CSS, 140_000],
]);

test('performance budget bundleBudget throws InvalidArgumentException for unknown metric', function (): void {
    expect(fn (): int => PerformanceBudget::bundleBudget(Profile::FULL, 'non_existent_metric'))
        ->toThrow(InvalidArgumentException::class, 'Unknown bundle metric [non_existent_metric].');
});

test('performance budget assertWithinQueryBudget passes within threshold and throws on regression', function (): void {
    expect(fn () => PerformanceBudget::assertWithinQueryBudget(Profile::MINIMAL, PerformanceBudget::REQUEST_WELCOME, 0))->not->toThrow(RuntimeException::class);
    expect(fn () => PerformanceBudget::assertWithinQueryBudget(Profile::FULL, PerformanceBudget::REQUEST_DASHBOARD, 4))->not->toThrow(RuntimeException::class);

    expect(fn () => PerformanceBudget::assertWithinQueryBudget(Profile::MINIMAL, PerformanceBudget::REQUEST_WELCOME, 2, ['SELECT * FROM settings', 'SELECT * FROM users']))
        ->toThrow(RuntimeException::class, 'Query count regression on [GET /] for profile [minimal]: expected at most 0 queries, got 2 queries.');

    expect(fn () => PerformanceBudget::assertWithinQueryBudget(Profile::MINIMAL, PerformanceBudget::REQUEST_WELCOME, 1, []))
        ->toThrow(RuntimeException::class, 'Query count regression on [GET /] for profile [minimal]: expected at most 0 queries, got 1 queries.');
});

test('performance budget assertWithinQueryBudget includes SQL statements in failure message', function (): void {
    try {
        PerformanceBudget::assertWithinQueryBudget(
            Profile::FULL,
            PerformanceBudget::REQUEST_DASHBOARD,
            5,
            ['SELECT 1', 'SELECT 2']
        );
        test()->fail('Expected RuntimeException was not thrown.');
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toContain('Query count regression on [GET /dashboard]')
            ->and($runtimeException->getMessage())->toContain('Executed SQL:')
            ->and($runtimeException->getMessage())->toContain('SELECT 1')
            ->and($runtimeException->getMessage())->toContain('SELECT 2');
    }
});

test('performance budget assertWithinBundleBudget passes within threshold and throws when exceeded', function (): void {
    expect(fn () => PerformanceBudget::assertWithinBundleBudget(Profile::FULL, PerformanceBudget::METRIC_ENTRY_JS, 90_000))->not->toThrow(RuntimeException::class);

    expect(fn () => PerformanceBudget::assertWithinBundleBudget(Profile::FULL, PerformanceBudget::METRIC_ENTRY_JS, 100_000))
        ->toThrow(RuntimeException::class, 'Bundle size budget exceeded for [entry_js] in profile [full]: expected at most 95000 bytes, got 100000 bytes (+5000 bytes).');
});

test('performance budget measureFrontendBundle reads real manifest and computes stats', function (): void {
    $tempDir = sys_get_temp_dir().'/perf_manifest_test_'.uniqid();
    mkdir($tempDir.'/public/build/assets', 0777, true);

    try {
        file_put_contents($tempDir.'/public/build/assets/app.js', 'console.log("hello");');
        file_put_contents($tempDir.'/public/build/assets/app.css', 'body { color: red; }');
        file_put_contents($tempDir.'/public/build/assets/chunk.js', 'export const x = 1;');

        $manifestData = [
            'resources/js/app.tsx' => ['file' => 'assets/app.js', 'isEntry' => true],
            'resources/css/app.css' => ['file' => 'assets/app.css', 'isEntry' => true],
            'resources/js/pages/welcome.tsx' => ['file' => 'assets/chunk.js'],
            'missing_file' => ['isEntry' => false],
            'absent_on_disk' => ['file' => 'assets/does_not_exist.js'],
        ];

        file_put_contents($tempDir.'/public/build/manifest.json', json_encode($manifestData));

        $stats = PerformanceBudget::measureFrontendBundle($tempDir);

        expect($stats[PerformanceBudget::METRIC_ENTRY_JS])->toBe(filesize($tempDir.'/public/build/assets/app.js'))
            ->and($stats[PerformanceBudget::METRIC_ENTRY_CSS])->toBe(filesize($tempDir.'/public/build/assets/app.css'))
            ->and($stats[PerformanceBudget::METRIC_TOTAL_JS])->toBe(
                filesize($tempDir.'/public/build/assets/app.js') + filesize($tempDir.'/public/build/assets/chunk.js')
            )
            ->and($stats[PerformanceBudget::METRIC_TOTAL_CSS])->toBe(filesize($tempDir.'/public/build/assets/app.css'))
            ->and($stats['chunks'])->toHaveCount(3);
    } finally {
        exec('rm -rf '.escapeshellarg($tempDir));
    }
});

test('performance budget measureFrontendBundle throws when manifest is missing or invalid', function (): void {
    $emptyDir = sys_get_temp_dir().'/perf_manifest_empty_'.uniqid();
    mkdir($emptyDir, 0777, true);

    try {
        expect(fn (): array => PerformanceBudget::measureFrontendBundle($emptyDir))
            ->toThrow(RuntimeException::class, 'Vite manifest not found');

        mkdir($emptyDir.'/public/build', 0777, true);
        file_put_contents($emptyDir.'/public/build/manifest.json', 'invalid-json');

        expect(fn (): array => PerformanceBudget::measureFrontendBundle($emptyDir))
            ->toThrow(RuntimeException::class, 'Failed to parse Vite manifest');
    } finally {
        exec('rm -rf '.escapeshellarg($emptyDir));
    }
});

test('performance budget measureFrontendBundle throws when entry script or stylesheet is missing', function (): void {
    $tempDir = sys_get_temp_dir().'/perf_manifest_missing_entry_'.uniqid();
    mkdir($tempDir.'/public/build/assets', 0777, true);

    try {
        file_put_contents($tempDir.'/public/build/assets/app.css', 'body {}');
        file_put_contents($tempDir.'/public/build/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/app.css'],
        ]));

        expect(fn (): array => PerformanceBudget::measureFrontendBundle($tempDir))
            ->toThrow(RuntimeException::class, 'Entry script [resources/js/app.tsx] not found in Vite manifest.');

        file_put_contents($tempDir.'/public/build/assets/app.js', 'console.log(1);');
        file_put_contents($tempDir.'/public/build/manifest.json', json_encode([
            'resources/js/app.tsx' => ['file' => 'assets/app.js'],
        ]));

        expect(fn (): array => PerformanceBudget::measureFrontendBundle($tempDir))
            ->toThrow(RuntimeException::class, 'Entry stylesheet [resources/css/app.css] not found in Vite manifest.');
    } finally {
        exec('rm -rf '.escapeshellarg($tempDir));
    }
});

test('performance budget generateHttpQueryMeasurementScript returns valid PHP string', function (): void {
    $script = PerformanceBudget::generateHttpQueryMeasurementScript();

    expect($script)->toBeString()
        ->and($script)->toContain('HandleInertiaRequests')
        ->and($script)->toContain('DB::enableQueryLog()')
        ->and($script)->toContain(PerformanceBudget::REQUEST_WELCOME)
        ->and($script)->toContain(PerformanceBudget::REQUEST_LOGIN)
        ->and($script)->toContain(PerformanceBudget::REQUEST_DASHBOARD)
        ->and($script)->toContain('json_encode($results)');
});

test('performance budget allBaselines returns complete structured schema', function (): void {
    $baselines = PerformanceBudget::allBaselines();

    expect($baselines['schema_version'])->toBe(PerformanceBudget::SCHEMA_VERSION)
        ->and($baselines['canonical_requests'])->toBe(PerformanceBudget::CANONICAL_REQUESTS)
        ->and($baselines['bundle_metrics'])->toBe(PerformanceBudget::BUNDLE_METRICS)
        ->and($baselines['profiles'])->toHaveKeys([Profile::MINIMAL, Profile::DEFAULT, Profile::FULL]);
});
