<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

afterEach(function (): void {
    TrustProxies::flushState();
});

it('sends baseline security headers', function (): void {
    $this->get(route('home'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('only sends HSTS in production for secure requests', function (): void {
    $this->get(route('home'))->assertHeaderMissing('Strict-Transport-Security');

    TrustProxies::at('*');
    app()->detectEnvironment(fn (): string => 'production');
    $this->get(route('home'), ['X-Forwarded-Proto' => 'https'])
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('omits HSTS in production when X-Forwarded-Proto comes from untrusted proxy', function (): void {
    TrustProxies::at('192.168.1.1');
    app()->detectEnvironment(fn (): string => 'production');

    $this->get(route('home'), [
        'REMOTE_ADDR' => '10.0.0.1',
        'X-Forwarded-Proto' => 'https',
    ])->assertHeaderMissing('Strict-Transport-Security');
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

it('does not load third-party font hosts', function (): void {
    expect(file_get_contents(resource_path('views/app.blade.php')))->not->toContain('fonts.bunny.net');
});
