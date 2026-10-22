<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final readonly class HandleAppearance
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appearance = $request->cookie('appearance');

        if (! is_string($appearance) || ! in_array($appearance, ['light', 'dark', 'system'], true)) {
            $appearance = 'system';
        }

        View::share('appearance', $appearance);

        return $next($request);
    }
}
