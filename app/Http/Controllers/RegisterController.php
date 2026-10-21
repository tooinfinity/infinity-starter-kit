<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateUser;
use App\Http\Requests\CreateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class RegisterController
{
    public function create(): Response
    {
        return Inertia::render('user/create');
    }

    public function store(CreateUserRequest $request, CreateUser $action): RedirectResponse
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->safe()->except('password');

        $cookieLocale = $request->cookie('locale');
        if (is_string($cookieLocale)) {
            $attributes['locale'] = $cookieLocale;
        }

        $user = $action->handle(
            $attributes,
            $request->string('password')->value(),
        );

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
