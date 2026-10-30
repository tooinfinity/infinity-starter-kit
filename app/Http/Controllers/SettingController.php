<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateSettings;
use App\Enums\SettingKey;
use App\Http\Requests\EditSettingsRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Queries\SettingQuery;
use Exception;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SettingController
{
    public function edit(EditSettingsRequest $request, SettingQuery $query): Response
    {
        try {
            $settings = $query->all();
        } catch (Exception $exception) {
            report($exception);
            $settings = null;
        }

        return Inertia::render('settings/application/edit', [
            'settings' => $settings === null ? null : [
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
