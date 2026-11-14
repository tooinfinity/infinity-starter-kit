<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

it('shares valid appearance mode from cookie with views', function (string $appearance): void {
    $middleware = new HandleAppearance;

    $request = Request::create('/', 'GET');
    $request->cookies->set('appearance', $appearance);

    $response = $middleware->handle($request, fn ($req): Response => response('OK'));

    expect(View::shared('appearance'))->toBe($appearance)
        ->and($response->getContent())->toBe('OK');
})->with([
    'dark mode' => 'dark',
    'light mode' => 'light',
    'system mode' => 'system',
]);

it('falls back to system appearance when cookie is missing or invalid', function (?string $cookieValue): void {
    $middleware = new HandleAppearance;

    $request = Request::create('/', 'GET');
    if ($cookieValue !== null) {
        $request->cookies->set('appearance', $cookieValue);
    }

    $response = $middleware->handle($request, fn ($req): Response => response('OK'));

    expect(View::shared('appearance'))->toBe('system')
        ->and($response->getContent())->toBe('OK');
})->with([
    'missing cookie' => null,
    'invalid cookie value' => 'malicious-or-unknown',
]);
