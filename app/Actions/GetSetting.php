<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final readonly class GetSetting
{
    public function handle(SettingKey $key): mixed
    {
        return Cache::remember(
            'settings.'.$key->value,
            now()->addHour(),
            fn (): mixed => Setting::query()
                ->where('key', $key->value)
                ->value('value') ?? $key->defaultValue(),
        );
    }
}
