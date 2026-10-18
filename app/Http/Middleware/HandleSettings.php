<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\GetSetting;
use App\Enums\SettingKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class HandleSettings
{
    public function __construct(private GetSetting $getSetting) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $name = $this->getSetting->handle(SettingKey::ApplicationName);

            if (is_string($name) && $name !== '') {
                config(['app.name' => $name]);
            }

            $timezone = $this->getSetting->handle(SettingKey::ApplicationTimezone);

            if (is_string($timezone) && $timezone !== '') {
                config(['app.display_timezone' => $timezone]);
            }
        } catch (Throwable) {
            // Keep default configuration if settings cannot be loaded.
        }

        return $next($request);
    }
}
