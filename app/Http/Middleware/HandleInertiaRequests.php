<?php

declare(strict_types=1);

namespace App\Http\Middleware;

/* @chisel-localization */
use App\Enums\Locale;
use App\Models\User;
/* @end-chisel-localization */
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                /* @chisel-roles-permissions */
                'permissions' => $user instanceof User ? $user->getAllPermissions()->pluck('name')->toArray() : [],
                'roles' => $user instanceof User ? $user->getRoleNames()->toArray() : [],
                /* @end-chisel-roles-permissions */
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            /* @chisel-localization */
            'locale' => app()->getLocale(),
            'direction' => Locale::tryFrom(app()->getLocale())?->direction() ?? 'ltr',
            'supportedLocales' => Locale::toOptions(),
            /* @end-chisel-localization */
            /* @chisel-notifications */
            'notifications' => [
                'unreadCount' => $user instanceof User ? $user->unreadNotifications()->count() : 0,
                'recent' => $user instanceof User
                    ? $user->notifications()
                        ->latest()
                        ->limit(5)
                        ->get()
                        ->map(fn (DatabaseNotification $notification): array => [
                            'id' => $notification->id,
                            'type' => $notification->data['type'] ?? null,
                            'title' => $notification->data['title'] ?? '',
                            'body' => $notification->data['body'] ?? '',
                            'icon' => $notification->data['icon'] ?? null,
                            'action' => $notification->data['action'] ?? null,
                            'read_at' => $notification->read_at?->toISOString(),
                            'created_at' => $notification->created_at?->toISOString() ?? '',
                        ])
                        ->all()
                    : [],
            ],
            /* @end-chisel-notifications */
        ];
    }
}
