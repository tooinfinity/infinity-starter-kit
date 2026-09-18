<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Notifications\DeleteNotification;
use App\Actions\Notifications\MarkNotificationAsRead;
use App\Http\Requests\Notifications\DeleteNotificationRequest;
use App\Http\Requests\Notifications\MarkNotificationAsReadRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/* @chisel-notifications */

final readonly class NotificationController
{
    public function index(#[CurrentUser] User $user): Response
    {
        $notifications = $user->notifications()
            ->latest()
            ->paginate(15)
            ->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->data['type'] ?? null,
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'icon' => $notification->data['icon'] ?? null,
                'action' => $notification->data['action'] ?? null,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at?->toISOString() ?? '',
            ]);

        return Inertia::render('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    public function update(
        MarkNotificationAsReadRequest $request,
        string $notification,
        MarkNotificationAsRead $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $notification);

        return back();
    }

    public function destroy(
        DeleteNotificationRequest $request,
        string $notification,
        DeleteNotification $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $action->handle($user, $notification);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('notifications.deleted'),
        ]);

        return back();
    }
}

/* @end-chisel-notifications */
