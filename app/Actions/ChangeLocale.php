<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Locale;
use App\Models\User;

/* @chisel-localization */

final readonly class ChangeLocale
{
    public function handle(User $user, Locale $locale): void
    {
        $user->update(['locale' => $locale]);
    }
}

/* @end-chisel-localization */
