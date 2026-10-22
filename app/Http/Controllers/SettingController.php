<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateSettings;
use App\Enums\SettingKey;
use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SettingController
{
    public function edit(Request $request): Response
    {
        /** @var array<string, mixed> $settings */
        $settings = $request->attributes->get('settings', []);

        return Inertia::render('settings/application/edit', [
            'settings' => [
                SettingKey::ApplicationName->value => $settings[SettingKey::ApplicationName->value] ?? SettingKey::ApplicationName->defaultValue(),
                SettingKey::ApplicationTimezone->value => $settings[SettingKey::ApplicationTimezone->value] ?? SettingKey::ApplicationTimezone->defaultValue(),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettings $action): RedirectResponse
    {
        $action->handle($request->validatedSettings());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Settings updated.'),
        ]);

        return to_route('settings.edit');
    }
}
