<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporting;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/* @chisel-reporting */

final class ExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ReportsExport->value) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'string'],
            'event' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}

/* @end-chisel-reporting */
