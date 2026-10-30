<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\UpdateUserStatus;
use App\Http\Requests\Users\ActivateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class ActivateUserController
{
    public function __invoke(ActivateUserRequest $request, User $user, UpdateUserStatus $action): RedirectResponse
    {
        $action->handle($user, true);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User activated successfully.'),
        ]);

        return back();
    }
}
