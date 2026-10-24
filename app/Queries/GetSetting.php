<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final readonly class GetSetting
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(
            'settings.all',
            now()->addHour(),
            /** @return array<string, mixed> */
            fn (): array => Setting::query()
                ->whereIn('key', SettingKey::values())
                ->get()
                ->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key->value => $setting->value,
                ])
                ->all(),
        );
    }

    public function handle(SettingKey $key): mixed
    {
        return $this->all()[$key->value] ?? $key->defaultValue();
    }
}
