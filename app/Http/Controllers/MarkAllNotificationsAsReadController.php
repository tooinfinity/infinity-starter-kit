<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Notifications\MarkAllNotificationsAsRead;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class MarkAllNotificationsAsReadController
{
    public function __invoke(#[CurrentUser] User $user, MarkAllNotificationsAsRead $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('notifications.all_marked_as_read'),
        ]);

        return back();
    }
}
