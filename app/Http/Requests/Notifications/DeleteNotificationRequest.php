<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class DeleteNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('notification');

        if (! is_string($id) || ! Str::isUuid($id)) {
            return false;
        }

        $notification = $this->user()?->notifications()->find($id);

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
