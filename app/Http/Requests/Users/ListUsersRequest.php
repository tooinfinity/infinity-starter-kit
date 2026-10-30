<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

final class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || ($this->user()?->can(Permission::UsersView->value) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ];
    }
}
