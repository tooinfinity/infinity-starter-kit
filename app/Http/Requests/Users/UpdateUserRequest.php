<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Data\Users\UpdateUserData;
use App\Enums\Permission;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\CanAssignRoles;
use App\Rules\NotLastSuperAdmin;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
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
        $target = $this->targetUser();
        $userId = $target instanceof User ? $target->id : (string) $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'email',
                new ValidEmail,
                Rule::unique(User::class)->ignore($userId),
            ],
            'roles' => [
                'sometimes',
                'array',
                new CanAssignRoles($this->user(), $target),
                new NotLastSuperAdmin($target, SuperAdminRestrictionOperation::RemoveRole),
            ],
            'roles.*' => ['string', ...(Schema::hasTable('roles') ? ['exists:roles,name'] : [])],
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

    public function toData(): UpdateUserData
    {
        /** @var array{name: string, email: string, roles?: list<string>} $validated */
        $validated = $this->validated();

        $canManageRoles = ! enum_exists(Permission::class) || ($this->user()?->can(Permission::UsersManageRoles->value) ?? false);

        return new UpdateUserData(
            name: $validated['name'],
            email: $validated['email'],
            roles: ($canManageRoles && array_key_exists('roles', $validated)) ? $validated['roles'] : null,
        );
    }
}
