<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Data\Users\CreateUserData;
use App\Enums\Permission;
use App\Models\User;
use App\Rules\CanAssignRoles;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || (($this->user()?->can(Permission::UsersView->value) ?? false)
            && $this->user()->can(Permission::UsersCreate->value));
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
            'roles' => ['sometimes', 'array', new CanAssignRoles($this->user())],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
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
