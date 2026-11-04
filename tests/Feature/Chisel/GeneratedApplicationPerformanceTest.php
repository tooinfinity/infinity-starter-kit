<?php

declare(strict_types=1);

namespace Tests\Feature\Chisel;

use App\Chisel\Installer\PerformanceBudget;
use App\Chisel\Installer\Profile;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

describe('Generated Application Performance Budgets', function (): void {
    beforeEach(function (): void {
        User::query()->count();
    });
    it('verifies that root application frontend production bundle satisfies full profile budgets', function (): void {
        $stats = PerformanceBudget::measureFrontendBundle(base_path());

        foreach (PerformanceBudget::BUNDLE_METRICS as $metric) {
            $actual = $stats[$metric];
            $budget = PerformanceBudget::bundleBudget(Profile::FULL, $metric);

            expect($actual)->toBeLessThanOrEqual(
                $budget,
                "Root build bundle size exceeded for [{$metric}]: expected at most {$budget} bytes, got {$actual} bytes."
            );
        }
    });

    it('verifies that canonical requests satisfy full profile query budgets', function (): void {
        config(['session.driver' => 'array', 'cache.default' => 'array']);

        // 1. GET /
        DB::flushQueryLog();
        DB::enableQueryLog();
        $welcomeResponse = $this->get('/');
        $welcomeResponse->assertOk();

        $welcomeQueries = count(DB::getQueryLog());
        PerformanceBudget::assertWithinQueryBudget(
            Profile::FULL,
            PerformanceBudget::REQUEST_WELCOME,
            $welcomeQueries,
            array_column(DB::getQueryLog(), 'query'),
        );

        // 2. GET /login
        DB::flushQueryLog();
        DB::enableQueryLog();
        $inertiaMiddleware = resolve(HandleInertiaRequests::class);
        $headers = ['X-Inertia' => 'true'];
        $version = $inertiaMiddleware->version(Request::create('/login', 'GET'));
        if ($version !== null) {
            $headers['X-Inertia-Version'] = $version;
        }

        $loginResponse = $this->withHeaders($headers)->get('/login');
        $loginResponse->assertOk();

        $loginQueries = count(DB::getQueryLog());
        PerformanceBudget::assertWithinQueryBudget(
            Profile::FULL,
            PerformanceBudget::REQUEST_LOGIN,
            $loginQueries,
            array_column(DB::getQueryLog(), 'query'),
        );

        // 3. GET /dashboard
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $dashResponse = $this->actingAs($user)->get('/dashboard');
        $dashResponse->assertOk();

        $dashQueries = count(DB::getQueryLog());
        PerformanceBudget::assertWithinQueryBudget(
            Profile::FULL,
            PerformanceBudget::REQUEST_DASHBOARD,
            $dashQueries,
            array_column(DB::getQueryLog(), 'query'),
        );
    });

    it('detects query count regressions when excessive queries are introduced', function (): void {
        expect(fn () => PerformanceBudget::assertWithinQueryBudget(
            Profile::MINIMAL,
            PerformanceBudget::REQUEST_WELCOME,
            5,
            ['SELECT * FROM unindexed_table', 'SELECT * FROM settings']
        ))->toThrow(RuntimeException::class, 'Query count regression on [GET /] for profile [minimal]: expected at most 0 queries, got 5 queries.');
    });

    it('detects bundle size regressions when production bundle exceeds allocated byte budget', function (): void {
        expect(fn () => PerformanceBudget::assertWithinBundleBudget(
            Profile::FULL,
            PerformanceBudget::METRIC_ENTRY_JS,
            120_000,
        ))->toThrow(RuntimeException::class, 'Bundle size budget exceeded for [entry_js] in profile [full]: expected at most 95000 bytes, got 120000 bytes (+25000 bytes).');
    });
});
