<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

it('sends baseline security headers', function (): void {
    $this->get(route('home'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('only sends HSTS in production', function (): void {
    $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');

    app()->detectEnvironment(fn (): string => 'production');
    $this->get(route('home'), ['X-Forwarded-Proto' => 'https'])
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('omits HSTS in production for non-secure requests', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->get(route('home'))
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('sends HSTS in production when request is secure', function (): void {
    $middleware = new SecurityHeaders();
    $request = Request::create('https://example.com/', 'GET');

    app()->detectEnvironment(fn (): string => 'production');

    $response = $middleware->handle($request, fn (): Response => response('OK'));

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains');
});

it('sends content security policy header with vite nonce', function (): void {
    $response = $this->get(route('home'));

    $response->assertHeader('Content-Security-Policy');

    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("script-src 'self' 'nonce-")
        ->and($csp)->toContain("'unsafe-eval'")
        ->and($csp)->toContain("style-src 'self' 'unsafe-inline'")
        ->and($csp)->toContain("img-src 'self' data: blob:")
        ->and($csp)->toContain("font-src 'self' data:")
        ->and($csp)->toContain("connect-src 'self' ws: wss: http: https:")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("form-action 'self'");
});

it('formats content security policy strictly in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $response = $this->get(route('home'), ['X-Forwarded-Proto' => 'https']);
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain("'unsafe-eval'")
        ->and($csp)->toContain("connect-src 'self';");
});

it('provides a fresh nonce during rendering that matches the content security policy', function (): void {
    $previousNonce = Vite::useCspNonce();

    foreach (range(1, 2) as $attempt) {
        $response = $this->get(route('home'));
        $nonce = Vite::cspNonce();

        expect($nonce)->toBeString()->not->toBeEmpty()->not->toBe($previousNonce)
            ->and($response->headers->get('Content-Security-Policy'))->toContain("'nonce-{$nonce}'")
            ->and($response->getContent())->toContain('nonce="'.$nonce.'"');

        $previousNonce = $nonce;
    }
});

it('can disable content security policy via config', function (): void {
    config(['security.csp.enabled' => false]);

    $this->get(route('home'))
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('supports content security policy report-only mode and report-uri', function (): void {
    config([
        'security.csp.report_only' => true,
        'security.csp.report_uri' => 'https://example.com/csp-report',
    ]);

    $response = $this->get(route('home'));

    $response->assertHeaderMissing('Content-Security-Policy');
    $response->assertHeader('Content-Security-Policy-Report-Only');

    $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');
    expect($csp)->toContain('report-uri https://example.com/csp-report');
});

it('merges extra sources from security config into content security policy', function (): void {
    config([
        'security.csp.extra_sources' => [
            'script_src' => ['https://scripts.example.com'],
            'connect_src' => ['https://api.example.com'],
            'img_src' => ['https://images.example.com'],
            'frame_src' => ['https://embed.example.com'],
        ],
    ]);

    $response = $this->get(route('home'));
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->and($csp)->toContain('https://scripts.example.com')
        ->and($csp)->toContain('connect-src')
        ->and($csp)->toContain('https://api.example.com')
        ->and($csp)->toContain('https://images.example.com')
        ->and($csp)->toContain("frame-src 'self' https://embed.example.com");
});

it('does not load third-party font hosts', function (): void {
    expect(file_get_contents(resource_path('views/app.blade.php')))->not->toContain('fonts.bunny.net');
});
