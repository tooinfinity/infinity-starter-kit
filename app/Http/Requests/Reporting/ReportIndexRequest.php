<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporting;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

final class ReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! enum_exists(Permission::class) || ($this->user()?->can(Permission::ReportsView->value) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
