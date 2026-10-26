<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

afterEach(function (): void {
    TrustProxies::flushState();
});

it('resolves client ip via trusted proxy wildcard', function (): void {
    TrustProxies::at('*');

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

it('resolves client ip via specific trusted proxy ip', function (): void {
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

it('ignores forwarded headers from untrusted proxies', function (): void {
    TrustProxies::at('10.0.0.2');

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
