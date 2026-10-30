<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The IP addresses of proxies that should be trusted when detecting the
    | client IP address, URL scheme, and other forwarded request headers.
    | Set to '*' or comma-separated IP addresses/CIDR blocks.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

];
