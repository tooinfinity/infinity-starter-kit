<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Enums\NotificationType;
use App\Http\Requests\Notifications\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/* @chisel-notifications */

final readonly class NotificationPreferenceController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $existingPreferences = $user->notificationPreferences
            ->keyBy(fn (NotificationPreference $pref): string => $pref->notification_type->value);

        $preferences = collect(NotificationType::cases())->map(function (NotificationType $type) use ($existingPreferences): array {
            $pref = $existingPreferences->get($type->value);

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'mandatory' => $type->isMandatory(),
                'database_enabled' => $type->isMandatory() || ($pref !== null ? $pref->database_enabled : true),
            ];
        })->all();

        return Inertia::render('settings/notifications/edit', [
            'preferences' => $preferences,
        ]);
    }

    public function update(
        UpdateNotificationPreferencesRequest $request,
        #[CurrentUser] User $user,
        UpdateNotificationPreferences $action,
    ): RedirectResponse {
        $action->handle($user, $request->validatedPreferences());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('notifications.preferences_updated'),
        ]);

        return back();
    }
}

/* @end-chisel-notifications */
