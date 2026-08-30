<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\GetSetting;
use App\Actions\UpdateSettings;
use App\Enums\SettingKey;
use App\Http\Requests\UpdateSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SettingController
{
    public function edit(GetSetting $getSetting): Response
    {
        return Inertia::render('settings/application/edit', [
            'settings' => [
                SettingKey::ApplicationName->value => $getSetting->handle(SettingKey::ApplicationName),
                SettingKey::ApplicationTimezone->value => $getSetting->handle(SettingKey::ApplicationTimezone),
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
