<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/* @chisel-notifications */

final class DeleteNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $notification = $this->user()?->notifications()->find($this->route('notification'));

        return $notification !== null;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}

/* @end-chisel-notifications */
