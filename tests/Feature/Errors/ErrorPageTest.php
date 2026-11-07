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

test('403 error on Inertia visit renders errors/show component with status 403', function (): void {
    $this->withHeaders(inertiaHeaders())
        ->get('/test-error/403')
        ->assertStatus(403)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/show')
        ->assertJsonPath('props.status', 403);
});

test('404 error on Inertia visit renders errors/show component with status 404', function (): void {
    $this->withHeaders(inertiaHeaders())
        ->get('/non-existent-route-404')
        ->assertStatus(404)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/show')
        ->assertJsonPath('props.status', 404);
});

test('419 token mismatch on Inertia visit renders errors/show component with status 419', function (): void {
    $this->withHeaders(inertiaHeaders())
        ->get('/test-error/419-token-mismatch')
        ->assertStatus(419)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/show')
        ->assertJsonPath('props.status', 419);
});

test('419 abort on Inertia visit renders errors/show component with status 419', function (): void {
    $this->withHeaders(inertiaHeaders())
        ->get('/test-error/419-abort')
        ->assertStatus(419)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/show')
        ->assertJsonPath('props.status', 419);
});

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

test('503 error on Inertia visit renders errors/show component with status 503', function (): void {
    $this->withHeaders(inertiaHeaders())
        ->get('/test-error/503')
        ->assertStatus(503)
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'errors/show')
        ->assertJsonPath('props.status', 503);
});

test('direct HTML request to 404 renders errors/show inside root template', function (): void {
    $this->get('/non-existent-route-404')
        ->assertStatus(404)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 404)
        );
});

test('direct HTML request to 403 renders errors/show inside root template', function (): void {
    $this->get('/test-error/403')
        ->assertStatus(403)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 403)
        );
});

test('direct HTML request to 419 renders errors/show inside root template', function (): void {
    $this->get('/test-error/419-abort')
        ->assertStatus(419)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 419)
        );
});

test('direct HTML request to 500 when debug is disabled renders errors/show inside root template', function (): void {
    config(['app.debug' => false]);

    $this->get('/test-error/500')
        ->assertStatus(500)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 500)
        );
});

test('direct HTML request to 503 renders errors/show inside root template', function (): void {
    $this->get('/test-error/503')
        ->assertStatus(503)
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('errors/show')
            ->where('status', 503)
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
