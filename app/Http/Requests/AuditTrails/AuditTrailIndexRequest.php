<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditTrails;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AuditTrailIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || ($this->user()?->can(Permission::AuditView->value) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', Rule::in([...AuditEvent::values(), 'all'])],
            'user_id' => ['nullable', 'string', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array{search?: string|null, event?: string|null, user_id?: string|null, date_from?: string|null, date_to?: string|null}
     */
    public function filters(): array
    {
        return [
            'search' => $this->filled('search') ? $this->string('search')->value() : null,
            'event' => $this->filled('event') ? $this->string('event')->value() : null,
            'user_id' => $this->filled('user_id') ? $this->string('user_id')->value() : null,
            'date_from' => $this->filled('date_from') ? $this->string('date_from')->value() : null,
            'date_to' => $this->filled('date_to') ? $this->string('date_to')->value() : null,
        ];
    }
}
