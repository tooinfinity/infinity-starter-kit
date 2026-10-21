<?php

declare(strict_types=1);

namespace App\Providers;

/* @chisel-roles-permissions */
use App\Enums\Role;
/* @end-chisel-roles-permissions */
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->min(12)->uncompromised()
                : $rule;
        });

        /* @chisel-roles-permissions */
        Gate::before(function (User $user, string $ability): ?true {
            if ($user->hasRole(Role::SuperAdmin->value)) {
                return true;
            }

            return null;
        });
        /* @end-chisel-roles-permissions */
    }
}
