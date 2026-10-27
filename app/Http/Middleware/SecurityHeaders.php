<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final readonly class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', config()->string('security.headers.x_content_type_options', 'nosniff'));
        $response->headers->set('Referrer-Policy', config()->string('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('X-Frame-Options', config()->string('security.headers.x_frame_options', 'DENY'));
        $response->headers->set('Permissions-Policy', config()->string('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=()'));

        if (app()->isProduction() && ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https')) {
            $response->headers->set('Strict-Transport-Security', config()->string('security.headers.strict_transport_security', 'max-age=31536000; includeSubDomains'));
        }

        if (config()->boolean('security.csp.enabled', true)) {
            /** @var array<string, array<int, string>> $extraSources */
            $extraSources = config('security.csp.extra_sources', []);

            /**
             * @param  array<int, string>  $defaultSources
             */
            $formatDirective = function (string $name, array $defaultSources, string $extraKey) use ($extraSources): string {
                /** @var array<int, string> $extra */
                $extra = $extraSources[$extraKey] ?? [];
                $sources = array_filter(array_merge($defaultSources, $extra));

                return $name.' '.implode(' ', $sources);
            };

            $scriptSources = ["'self'", "'nonce-{$nonce}'"];
            if (! app()->isProduction()) {
                $scriptSources[] = "'unsafe-eval'";
            }

            $connectSources = ["'self'"];
            if (! app()->isProduction()) {
                $connectSources = array_merge($connectSources, ['ws:', 'wss:', 'http:', 'https:']);
            }

            $csp = [
                "default-src 'self'",
                $formatDirective('script-src', $scriptSources, 'script_src'),
                $formatDirective('style-src', ["'self'", "'unsafe-inline'"], 'style_src'),
                $formatDirective('img-src', ["'self'", 'data:', 'blob:'], 'img_src'),
                $formatDirective('font-src', ["'self'", 'data:'], 'font_src'),
                $formatDirective('connect-src', $connectSources, 'connect_src'),
                $formatDirective('media-src', ["'self'"], 'media_src'),
                "object-src 'none'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ];

            if (! empty($extraSources['frame_src'])) {
                $csp[] = $formatDirective('frame-src', ["'self'"], 'frame_src');
            }

            $reportUri = config('security.csp.report_uri');
            if (is_string($reportUri) && $reportUri !== '') {
                $csp[] = "report-uri {$reportUri}";
            }

            $headerName = config()->boolean('security.csp.report_only', false)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($headerName, implode('; ', $csp));
        }

        return $response;
    }
}
