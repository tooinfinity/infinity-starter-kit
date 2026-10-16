<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Data\Users\UpdateUserData;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Traits\HasRoles;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || ($this->user()?->can(Permission::UsersUpdate->value) ?? false);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|string|null $targetUser */
        $targetUser = $this->route('user');
        $userId = $targetUser instanceof User ? $targetUser->id : (string) $targetUser;

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
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', ...(Schema::hasTable('roles') ? ['exists:roles,name'] : [])],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('roles')) {
                    return;
                }

                $actor = $this->user();
                $canManageRoles = ! enum_exists(Permission::class) || ($actor?->can(Permission::UsersManageRoles->value) ?? false);

                if ($canManageRoles && trait_exists(HasRoles::class) && enum_exists(Role::class)) {
                    $actorIsSuperAdmin = $actor?->hasRole(Role::SuperAdmin->value) ?? false;
                    $targetUser = $this->route('user');

                    if ($targetUser instanceof User) {
                        $targetHasSuperAdmin = $targetUser->hasRole(Role::SuperAdmin->value);
                        $submittedRoles = (array) $this->input('roles', []);
                        $submittedHasSuperAdmin = in_array(Role::SuperAdmin->value, $submittedRoles, true);

                        if (! $actorIsSuperAdmin) {
                            if ($submittedHasSuperAdmin && ! $targetHasSuperAdmin) {
                                $validator->errors()->add('roles', __('Only Super Admins can assign the Super Admin role.'));
                            } elseif (! $submittedHasSuperAdmin && $targetHasSuperAdmin) {
                                $validator->errors()->add('roles', __('Only Super Admins can remove the Super Admin role.'));
                            }
                        }
                    }
                }
            },
        ];
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
