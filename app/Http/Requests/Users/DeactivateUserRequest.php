<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\NotLastSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class DeactivateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->targetUser();

        if (! $actor instanceof User || ! $target instanceof User) {
            return false;
        }

        $canUpdate = ! enum_exists(Permission::class) || $actor->can(Permission::UsersUpdate->value);

        return $canUpdate && $actor->canManage($target);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $target = $this->targetUser();

                if ($actor instanceof User && $target instanceof User && $target->id === $actor->id) {
                    $validator->errors()->add('user', __('You cannot deactivate your own account.'));

                    return;
                }

                NotLastSuperAdmin::validateRequest($target, SuperAdminRestrictionOperation::Deactivate, $validator);
            },
        ];
    }

    public function targetUser(): ?User
    {
        $target = $this->route('user');

        if (is_string($target)) {
            $target = User::query()->find($target);
        }

        return $target instanceof User ? $target : null;
    }
}
