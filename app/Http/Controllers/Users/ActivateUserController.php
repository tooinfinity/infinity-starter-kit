<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\ActivateUserAction;
use App\Http\Requests\Users\ActivateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class ActivateUserController
{
    public function __invoke(ActivateUserRequest $request, User $user, ActivateUserAction $action): RedirectResponse
    {
        $action->handle($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User activated successfully.'),
        ]);

        return back();
    }
}
