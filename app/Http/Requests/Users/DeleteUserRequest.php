<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Actions\Users\EnsureActorMayManageTargetAction;
use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteUserRequest extends FormRequest
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

        $canDelete = ! enum_exists(Permission::class) || $actor->can(Permission::UsersDelete->value);

        return $canDelete && resolve(EnsureActorMayManageTargetAction::class)->handle($actor, $target);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
