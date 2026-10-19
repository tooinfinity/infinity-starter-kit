<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Actions\Users\EnsureActorMayManageTargetAction;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class UpdateUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        if (is_string($target)) {
            $target = User::query()->find($target);
        }

        if (! $actor instanceof User || ! $target instanceof User) {
            return false;
        }

        $canManagePassword = ! enum_exists(Permission::class) || $actor->can(Permission::UsersManagePassword->value);

        return $canManagePassword && resolve(EnsureActorMayManageTargetAction::class)->handle($actor, $target);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => [
                'required',
                Password::defaults(),
            ],
        ];
    }
}
