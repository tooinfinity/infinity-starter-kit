<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSettings
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function handle(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value],
                );

                Cache::forget('settings.'.$key);
            }
        });
    }

    public function set(SettingKey $key, mixed $value): void
    {
        $this->handle([$key->value => $value]);
    }
}
