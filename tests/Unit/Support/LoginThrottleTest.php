<?php

declare(strict_types=1);

use App\Support\LoginThrottle;

test('throttleKeyFor normalizes email case and whitespace and appends ip', function (): void {
    expect(LoginThrottle::throttleKeyFor('  User@Example.COM  ', '192.0.2.1'))
        ->toBe('user@example.com|192.0.2.1')
        ->and(LoginThrottle::throttleKeyFor('john@example.com', '127.0.0.1'))
        ->toBe('john@example.com|127.0.0.1');
});

test('maxAttempts resolves configured login rate limit with default fallback', function (): void {
    config()->set('fortify.rate_limits.login', 10);
    expect(LoginThrottle::maxAttempts())->toBe(10);

    config()->set('fortify.rate_limits.login', 3);
    expect(LoginThrottle::maxAttempts())->toBe(3);

    config()->set('fortify.rate_limits.login', 5);
    expect(LoginThrottle::maxAttempts())->toBe(5);
});
