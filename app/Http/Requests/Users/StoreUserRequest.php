<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Actions\Users\EnsureActorMayAssignRolesAction;
use App\Data\Users\CreateUserData;
use App\Enums\Permission;
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || ($this->user()?->can(Permission::UsersCreate->value) ?? false);
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'email',
                new ValidEmail,
                Rule::unique(User::class),
            ],
            'password' => [
                'required',
                Password::defaults(),
            ],
            'is_active' => ['sometimes', 'boolean'],
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

                resolve(EnsureActorMayAssignRolesAction::class)->handle(
                    $validator,
                    $this->user(),
                    (array) $this->input('roles', []),
                );
            },
        ];
    }

    public function toData(): CreateUserData
    {
        /** @var array{name: string, email: string, password: string, is_active?: bool, roles?: list<string>} $validated */
        $validated = $this->validated();

        $canManageRoles = ! enum_exists(Permission::class) || ($this->user()?->can(Permission::UsersManageRoles->value) ?? false);

        return new CreateUserData(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            isActive: $validated['is_active'] ?? true,
            roles: ($canManageRoles && array_key_exists('roles', $validated)) ? $validated['roles'] : [],
        );
    }
}
