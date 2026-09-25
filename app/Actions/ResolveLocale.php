<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Http\Request;

final readonly class ResolveLocale
{
    public function handle(Request $request): Locale
    {
        if ($request->user() instanceof User) {
            $userLocale = $request->user()->locale;

            if ($userLocale instanceof Locale) {
                return $userLocale;
            }

            return Locale::default();
        }

        $cookieLocale = $request->cookie('locale');

        if (is_string($cookieLocale)) {
            $locale = Locale::tryFrom($cookieLocale);

            if ($locale instanceof Locale) {
                return $locale;
            }
        }

        return Locale::default();
    }
}
