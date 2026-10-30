<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporting;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || (($this->user()?->can(Permission::ReportsView->value) ?? false)
            && $this->user()->can(Permission::ReportsExport->value));
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
            'event' => ['nullable', 'string', Rule::in([...AuditEvent::values(), 'all'])],
            'user_id' => ['nullable', 'string', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
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
}
