<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (CSP)
    |--------------------------------------------------------------------------
    |
    | Configuration for defensive Content-Security-Policy headers applied by
    | the SecurityHeaders middleware. You can enable or disable CSP, run it
    | in report-only mode, and add extra origin sources for analytics or CDNs.
    |
    */

    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),

        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),

        'report_to' => env('SECURITY_CSP_REPORT_TO'),

        'reporting_endpoints' => env('SECURITY_CSP_REPORTING_ENDPOINTS'),

        'report_uri' => env('SECURITY_CSP_REPORT_URI'),

        'extra_sources' => [
            'script_src' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SECURITY_CSP_SCRIPT_SRC', ''))), static fn (string $src): bool => $src !== '' && ! str_contains($src, ';') && ! str_contains($src, "\n") && ! str_contains($src, "\r"))),
            'style_src' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SECURITY_CSP_STYLE_SRC', ''))), static fn (string $src): bool => $src !== '' && ! str_contains($src, ';') && ! str_contains($src, "\n") && ! str_contains($src, "\r"))),
            'img_src' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SECURITY_CSP_IMG_SRC', ''))), static fn (string $src): bool => $src !== '' && ! str_contains($src, ';') && ! str_contains($src, "\n") && ! str_contains($src, "\r"))),
            'font_src' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SECURITY_CSP_FONT_SRC', ''))), static fn (string $src): bool => $src !== '' && ! str_contains($src, ';') && ! str_contains($src, "\n") && ! str_contains($src, "\r"))),
            'connect_src' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SECURITY_CSP_CONNECT_SRC', ''))), static fn (string $src): bool => $src !== '' && ! str_contains($src, ';') && ! str_contains($src, "\n") && ! str_contains($src, "\r"))),
            'media_src' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SECURITY_CSP_MEDIA_SRC', ''))), static fn (string $src): bool => $src !== '' && ! str_contains($src, ';') && ! str_contains($src, "\n") && ! str_contains($src, "\r"))),
            'frame_src' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SECURITY_CSP_FRAME_SRC', ''))), static fn (string $src): bool => $src !== '' && ! str_contains($src, ';') && ! str_contains($src, "\n") && ! str_contains($src, "\r"))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Security Headers
    |--------------------------------------------------------------------------
    |
    | Baseline response headers sent across all HTTP requests.
    |
    */

    'headers' => [
        'x_content_type_options' => 'nosniff',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'x_frame_options' => 'DENY',
        'permissions_policy' => 'camera=(), microphone=(), geolocation=()',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains',
    ],

];
