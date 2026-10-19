<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Actions\Users\EnsureActorMayAssignRolesAction;
use App\Actions\Users\EnsureActorMayManageTargetAction;
use App\Data\Users\UpdateUserData;
use App\Enums\Permission;
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateUserRequest extends FormRequest
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

        $canUpdate = ! enum_exists(Permission::class) || $actor->can(Permission::UsersUpdate->value);

        return $canUpdate && resolve(EnsureActorMayManageTargetAction::class)->handle($actor, $target);
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

                $target = $this->route('user');

                resolve(EnsureActorMayAssignRolesAction::class)->handle(
                    $validator,
                    $this->user(),
                    (array) $this->input('roles', []),
                    $target instanceof User ? $target : null,
                );
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
