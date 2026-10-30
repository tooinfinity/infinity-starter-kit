<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SettingKey;
use App\Queries\SettingQuery;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies dynamic application settings per web request.
 *
 * Dynamic settings (such as application name and display timezone) are applied
 * during the web middleware lifecycle. CLI commands and queued background workers
 * use the base environment configuration (e.g. .env and config/app.php).
 */
final readonly class HandleSettings
{
    public function __construct(private SettingQuery $settingQuery) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $settings = $this->settingQuery->all();

            $name = $settings[SettingKey::ApplicationName->value] ?? null;

            if (is_string($name) && $name !== '') {
                config(['app.name' => $name]);
            }

            $timezone = $settings[SettingKey::ApplicationTimezone->value] ?? null;

            if (is_string($timezone) && $timezone !== '') {
                config(['app.display_timezone' => $timezone]);
            }
        } catch (Exception $exception) {
            report($exception);
        }

        return $next($request);
    }
}
