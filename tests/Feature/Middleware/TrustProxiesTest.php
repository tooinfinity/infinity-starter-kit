<?php

declare(strict_types=1);

use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

afterEach(function (): void {
    TrustProxies::flushState();
});

it('resolves client ip and secure status via trusted proxy wildcard in config', function (): void {
    config(['security.trusted_proxies' => '*']);

    $middleware = new TrustProxies();
    $request = Request::create('http://localhost/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    $clientIp = null;
    $isSecure = false;
    $middleware->handle($request, function (Request $req) use (&$clientIp, &$isSecure): Response {
        $clientIp = $req->ip();
        $isSecure = $req->isSecure();

        return response('OK');
    });

    expect($clientIp)->toBe('203.0.113.9')
        ->and($isSecure)->toBeTrue();
});

it('resolves client ip via specific trusted proxy ip in config', function (): void {
    config(['security.trusted_proxies' => '10.0.0.1']);

    $middleware = new TrustProxies();
    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ]);

    $clientIp = null;
    $middleware->handle($request, function (Request $req) use (&$clientIp): Response {
        $clientIp = $req->ip();

        return response('OK');
    });

    expect($clientIp)->toBe('203.0.113.9');
});

it('resolves client ip via array of trusted proxy ips in config', function (): void {
    config(['security.trusted_proxies' => ['10.0.0.1', '10.0.0.2']]);

    $middleware = new TrustProxies();
    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.2',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ]);

    $clientIp = null;
    $middleware->handle($request, function (Request $req) use (&$clientIp): Response {
        $clientIp = $req->ip();

        return response('OK');
    });

    expect($clientIp)->toBe('203.0.113.9');
});

it('ignores forwarded headers from untrusted proxies in config', function (): void {
    config(['security.trusted_proxies' => '10.0.0.2']);

    $middleware = new TrustProxies();
    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ]);

    $clientIp = null;
    $middleware->handle($request, function (Request $req) use (&$clientIp): Response {
        $clientIp = $req->ip();

        return response('OK');
    });

    expect($clientIp)->toBe('10.0.0.1');
});

it('ignores forwarded headers when trusted proxies is unset in config', function (): void {
    config(['security.trusted_proxies' => null]);

    $middleware = new TrustProxies();
    $request = Request::create('http://localhost/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    $clientIp = null;
    $isSecure = true;
    $middleware->handle($request, function (Request $req) use (&$clientIp, &$isSecure): Response {
        $clientIp = $req->ip();
        $isSecure = $req->isSecure();

        return response('OK');
    });

    expect($clientIp)->toBe('10.0.0.1')
        ->and($isSecure)->toBeFalse();
});

it('ignores forwarded headers when trusted proxies config is not array or string', function (): void {
    config(['security.trusted_proxies' => false]);

    $middleware = new TrustProxies();
    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ]);

    $clientIp = null;
    $middleware->handle($request, function (Request $req) use (&$clientIp): Response {
        $clientIp = $req->ip();

        return response('OK');
    });

    expect($clientIp)->toBe('10.0.0.1');
});

it('prioritizes explicitly configured static proxies over config', function (): void {
    config(['security.trusted_proxies' => '10.0.0.2']);
    TrustProxies::at('10.0.0.1');

    $middleware = new TrustProxies();
    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
    ]);

    $clientIp = null;
    $middleware->handle($request, function (Request $req) use (&$clientIp): Response {
        $clientIp = $req->ip();

        return response('OK');
    });

    expect($clientIp)->toBe('203.0.113.9');
});

it('reflects forwarded values across full http request pipeline when trusted', function (): void {
    config(['security.trusted_proxies' => '*']);

    Route::get('/_test/trusted-proxies', fn (Request $request): Response => response()->json([
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]));

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->getJson('/_test/trusted-proxies');

    $response->assertOk()
        ->assertJson([
            'ip' => '203.0.113.9',
            'secure' => true,
        ]);
});

it('ignores forwarded values across full http request pipeline when unset', function (): void {
    config(['security.trusted_proxies' => null]);

    Route::get('/_test/trusted-proxies-unset', fn (Request $request): Response => response()->json([
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]));

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->getJson('/_test/trusted-proxies-unset');

    $response->assertOk()
        ->assertJson([
            'ip' => '10.0.0.1',
            'secure' => false,
        ]);
});
