<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Permission;
/* @chisel-settings-authorization */
use App\Enums\SettingKey;
/* @end-chisel-settings-authorization */
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /* @chisel-settings-authorization */
        $user = $this->user();

        return $user !== null && $user->can(Permission::SettingsManage->value);
        /* @end-chisel-settings-authorization */
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var array<string, mixed> $settings */
                $settings = $this->input('settings', []);
                $validKeys = SettingKey::values();

                foreach ($settings as $key => $value) {
                    if (! in_array($key, $validKeys, true)) {
                        $validator->errors()->add(
                            'settings.'.$key,
                            __('The setting key :key is not recognized.', ['key' => $key]),
                        );

                        continue;
                    }

                    $settingKey = SettingKey::from($key);
                    $rules = $settingKey->rules();

                    $keyValidator = \Illuminate\Support\Facades\Validator::make(
                        ['value' => $value],
                        ['value' => $rules],
                    );

                    if ($keyValidator->fails()) {
                        foreach ($keyValidator->errors()->all() as $message) {
                            $validator->errors()->add('settings.'.$key, $message);
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedSettings(): array
    {
        /** @var array<string, mixed> $settings */
        $settings = $this->input('settings', []);

        $validKeys = SettingKey::values();

        return array_filter(
            $settings,
            fn (mixed $value, string $key): bool => in_array($key, $validKeys, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
