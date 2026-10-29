<?php

declare(strict_types=1);

namespace App\Http\Requests;

/* @chisel-localization */
use App\Enums\Locale;
/* @end-chisel-localization */
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CreateUserRequest extends FormRequest
{
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
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    /* @chisel-localization */
    public function locale(): ?Locale
    {
        $locale = $this->cookie('locale');

        return is_string($locale) ? Locale::tryFrom($locale) : null;
    }

    /* @end-chisel-localization */
}
