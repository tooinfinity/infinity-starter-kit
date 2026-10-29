<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Override;

final class TrustProxies extends Middleware
{
    /**
     * Get the trusted proxies.
     *
     * @return array<int, string>|string|null
     */
    #[Override]
    public function proxies(): array|string|null
    {
        /** @var array<int, string>|string|null $proxies */
        $proxies = self::$alwaysTrustProxies ?? config('security.trusted_proxies');

        return is_array($proxies) || is_string($proxies) ? $proxies : null;
    }
}
