<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\ChangeUserPasswordAction;
use App\Http\Requests\Users\UpdateUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class UserPasswordController
{
    public function __invoke(UpdateUserPasswordRequest $request, User $user, ChangeUserPasswordAction $action): RedirectResponse
    {
        $action->handle($user, $request->string('password')->value());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User password updated successfully.'),
        ]);

        return back();
    }
}
