<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserLocale;
use App\Http\Requests\UpdateLocaleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

final readonly class LocaleController
{
    public function update(UpdateLocaleRequest $request, UpdateUserLocale $action): RedirectResponse
    {
        $locale = $request->validatedLocale();
        $user = $request->user();

        if ($user instanceof User) {
            $action->handle($user, $locale);
        }

        return back()
            ->withCookie(cookie('locale', $locale->value, 525600));
    }
}
