<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\ResolveLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final readonly class HandleLocale
{
    public function __construct(private ResolveLocale $resolveLocale) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale->handle($request);

        App::setLocale($locale->value);
        View::share('direction', $locale->direction());

        return $next($request);
    }
}
