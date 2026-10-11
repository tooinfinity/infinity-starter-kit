<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporting;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UserReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || ($this->user()?->can(Permission::ReportsView->value) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string', Rule::in(['all', 'active', 'inactive'])],
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'name', 'email'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('date_from') || $validator->errors()->has('date_to')) {
                    return;
                }

                $dateFromStr = $this->filled('date_from') ? $this->string('date_from')->value() : null;
                $dateToStr = $this->filled('date_to') ? $this->string('date_to')->value() : null;

                if ($dateFromStr !== null || $dateToStr !== null) {
                    $from = $dateFromStr !== null ? Date::parse($dateFromStr)->startOfDay() : Date::now()->subDays(29)->startOfDay();
                    $to = $dateToStr !== null ? Date::parse($dateToStr)->endOfDay() : Date::now()->endOfDay();

                    if ($from->diffInDays($to) > 366) {
                        $validator->errors()->add('date_to', __('The date range cannot exceed 366 days.'));
                    }
                }
            },
        ];
    }

    /**
     * @return array{date_from?: string|null, date_to?: string|null, status?: string|null, search?: string|null, sort?: string|null, direction?: string|null}
     */
    public function filters(): array
    {
        return [
            'date_from' => $this->filled('date_from') ? $this->string('date_from')->value() : null,
            'date_to' => $this->filled('date_to') ? $this->string('date_to')->value() : null,
            'status' => $this->filled('status') ? $this->string('status')->value() : null,
            'search' => $this->filled('search') ? $this->string('search')->value() : null,
            'sort' => $this->filled('sort') ? $this->string('sort')->value() : null,
            'direction' => $this->filled('direction') ? $this->string('direction')->value() : null,
        ];
    }
}
