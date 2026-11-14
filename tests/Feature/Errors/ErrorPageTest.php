<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/test-error/403', fn () => abort(403));
        Route::get('/test-error/404', fn () => abort(404));
        Route::get('/test-error/419-token-mismatch', fn (): never => throw new TokenMismatchException('CSRF token mismatch.'));
        Route::get('/test-error/419-abort', fn () => abort(419));
        Route::get('/test-error/500', fn (): never => throw new RuntimeException('Boom'));
        Route::get('/test-error/503', fn (): never => throw new HttpException(503, 'Service unavailable'));
    });
});

/**
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    /** @var HandleInertiaRequests $middleware */
    $middleware = resolve(HandleInertiaRequests::class);
    $version = $middleware->version(request());

    $headers = ['X-Inertia' => 'true'];
    if ($version !== null && $version !== '') {
        $headers['X-Inertia-Version'] = $version;
    }

    return $headers;
}

test('HTTP errors on Inertia visit render errors/show component with matching status', function (string $endpoint, int $status): void {
    $this->withHeaders(inertiaHeaders())
        ->get($endpoint)
        ->assertStatus($status)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/show')
        ->assertJsonPath('props.status', $status);
})->with([
    '403 forbidden' => ['/test-error/403', 403],
    '404 not found' => ['/non-existent-route-404', 404],
    '419 token mismatch' => ['/test-error/419-token-mismatch', 419],
    '419 abort' => ['/test-error/419-abort', 419],
    '503 service unavailable' => ['/test-error/503', 503],
]);

test('500 error on Inertia visit renders errors/show component when debug is disabled', function (): void {
    config(['app.debug' => false]);

    $this->withHeaders(inertiaHeaders())
        ->get('/test-error/500')
        ->assertStatus(500)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/show')
        ->assertJsonPath('props.status', 500);
});

test('500 error in debug mode keeps the detailed exception page', function (): void {
    config(['app.debug' => true]);

    $response = $this->withHeaders(inertiaHeaders())
        ->get('/test-error/500')
        ->assertStatus(500);

    expect($response->headers->has('X-Inertia'))->toBeFalse()
        ->and($response->getContent())->toContain('Boom');
});

test('direct HTML request to error endpoint renders errors/show inside root template', function (string $endpoint, int $status): void {
    $this->get($endpoint)
        ->assertStatus($status)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', $status)
        );
})->with([
    '404 not found' => ['/non-existent-route-404', 404],
    '403 forbidden' => ['/test-error/403', 403],
    '419 token mismatch / abort' => ['/test-error/419-abort', 419],
    '503 service unavailable' => ['/test-error/503', 503],
]);

test('direct HTML request to 500 when debug is disabled renders errors/show inside root template', function (): void {
    config(['app.debug' => false]);

    $this->get('/test-error/500')
        ->assertStatus(500)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 500)
        );
});

test('authenticated user sees error page with auth shared props on 404', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/test-error/404')
        ->assertStatus(404)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 404)
            ->where('auth.user.id', $user->id)
        );
});

test('guest user sees error page without errors on unmatched 404', function (): void {
    $this->get('/non-existent-route-404')
        ->assertStatus(404)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 404)
        );
});

test('JSON requests are unaffected and receive standard JSON response', function (): void {
    $this->getJson('/non-existent-route-404')
        ->assertStatus(404)
        ->assertJson([
            'message' => 'The route non-existent-route-404 could not be found.',
        ]);
});

test('non-HTML non-Inertia requests receive default response', function (): void {
    $response = $this->withHeaders([
        'Accept' => 'application/octet-stream',
    ])->get('/non-existent-route-404');

    expect($response->status())->toBe(404)
        ->and($response->headers->has('X-Inertia'))->toBeFalse();
});
