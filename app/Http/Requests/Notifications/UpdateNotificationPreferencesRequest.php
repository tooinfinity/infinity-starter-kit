<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Enums\NotificationType;
use Illuminate\Foundation\Http\FormRequest;

/* @chisel-notifications */

final class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*' => ['required', 'array'],
            'preferences.*.database_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, array{database_enabled: bool}>
     */
    public function validatedPreferences(): array
    {
        /** @var array<string, array{database_enabled: bool}> $preferences */
        $preferences = $this->validated('preferences');

        return array_filter(
            $preferences,
            fn (mixed $value, string $key): bool => NotificationType::tryFrom($key) !== null,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}

/* @end-chisel-notifications */
