<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/* @chisel-localization */

final class ChangeLocaleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(Locale::values())],
        ];
    }

    public function validatedLocale(): Locale
    {
        /** @var array{locale: string} $validated */
        $validated = $this->validated();

        return Locale::from($validated['locale']);
    }
}

/* @end-chisel-localization */
