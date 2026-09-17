<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\DeactivateUserAction;
use App\Http\Requests\Users\DeactivateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class DeactivateUserController
{
    public function __invoke(DeactivateUserRequest $request, User $user, DeactivateUserAction $action): RedirectResponse
    {
        $action->handle($user, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User deactivated successfully.'),
        ]);

        return back();
    }
}
