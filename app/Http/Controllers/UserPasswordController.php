<?php

declare(strict_types=1);

namespace App\Http\Controllers;

/* @chisel-password-reset */
use App\Actions\CreateUserPassword;
/* @end-chisel-password-reset */
/* @chisel-password-update */
use App\Actions\UpdateUserPassword;
/* @end-chisel-password-update */
/* @chisel-password-reset */
use App\Http\Requests\CreateUserPasswordRequest;
/* @end-chisel-password-reset */
/* @chisel-password-update */
use App\Http\Requests\UpdateUserPasswordRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
/* @end-chisel-password-update */
use Illuminate\Http\RedirectResponse;
/* @chisel-password-reset */
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
/* @end-chisel-password-reset */
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserPasswordController
{
    /* @chisel-password-reset */
    public function create(Request $request): Response
    {
        return Inertia::render('user-password/create', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    public function store(CreateUserPasswordRequest $request, CreateUserPassword $action): RedirectResponse
    {
        /** @var array<string, mixed> $credentials */
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $status = $action->handle(
            $credentials,
            $request->string('password')->value()
        );

        throw_if($status !== Password::PASSWORD_RESET, ValidationException::withMessages([
            'email' => [__(is_string($status) ? $status : '')],
        ]));

        return to_route('login')->with('status', __('passwords.reset'));
    }

    /* @end-chisel-password-reset */

    /* @chisel-password-update */
    public function edit(): Response
    {
        return Inertia::render('user-password/edit');
    }

    public function update(UpdateUserPasswordRequest $request, #[CurrentUser] User $user, UpdateUserPassword $action): RedirectResponse
    {
        $action->handle($user, $request->string('password')->value());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Password updated.'),
        ]);

        return back();
    }

    /* @end-chisel-password-update */
}
