<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmailVerificationRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class UserEmailVerificationController
{
    public function update(UpdateEmailVerificationRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        $request->fulfill();

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
