<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final readonly class LoginThrottle
{
    /**
     * Get the maximum number of login attempts allowed per minute.
     */
    public static function maxAttempts(): int
    {
        return config()->integer('fortify.rate_limits.login', 5);
    }

    /**
     * Get the rate-limiting throttle key for the given email and IP.
     */
    public static function throttleKeyFor(string $email, ?string $ip): string
    {
        return Str::of($email)
            ->trim()
            ->lower()
            ->append('|'.$ip)
            ->transliterate()
            ->value();
    }
}
