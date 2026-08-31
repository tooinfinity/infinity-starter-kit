<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Data\Users\UpdateUserData;
use App\Enums\Permission;
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UsersUpdate->value) ?? false;
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
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    public function toData(): UpdateUserData
    {
        /** @var array{name: string, email: string, is_active?: bool, roles?: list<string>} $validated */
        $validated = $this->validated();

        return new UpdateUserData(
            name: $validated['name'],
            email: $validated['email'],
            isActive: $validated['is_active'] ?? true,
            roles: $validated['roles'] ?? [],
        );
    }
}
