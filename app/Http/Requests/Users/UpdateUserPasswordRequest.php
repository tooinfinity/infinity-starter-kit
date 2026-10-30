<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class UpdateUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->targetUser();

        if (! $actor instanceof User || ! $target instanceof User) {
            return false;
        }

        $canManagePassword = ! enum_exists(Permission::class) || ($actor->can(Permission::UsersView->value)
            && $actor->can(Permission::UsersManagePassword->value));

        return $canManagePassword && $actor->canManage($target);
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

    public function targetUser(): ?User
    {
        $target = $this->route('user');

        if (is_string($target)) {
            $target = Str::isUuid($target) ? User::query()->find($target) : null;
        }

        return $target instanceof User ? $target : null;
    }
}
