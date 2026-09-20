<?php

declare(strict_types=1);

namespace App\Actions;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class UpdateSettings
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    /**
     * @param  array<string, mixed>  $settings
     */
    public function handle(array $settings): void
    {
        DB::transaction(function () use ($settings): void {
            foreach ($settings as $key => $value) {
                /* @chisel-audit-trails */
                $existingSetting = Setting::query()->where('key', $key)->first();
                $oldValue = $existingSetting?->value;
                /* @end-chisel-audit-trails */

                $setting = Setting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value],
                );

                Cache::forget('settings.'.$key);

                /* @chisel-audit-trails */
                $this->auditTrail->handle(
                    event: AuditEvent::SettingsUpdated,
                    auditable: $setting,
                    oldValues: ['key' => $key, 'value' => $oldValue],
                    newValues: ['key' => $key, 'value' => $value],
                    tags: ['settings'],
                );
                /* @end-chisel-audit-trails */
            }
        });
    }

    public function set(SettingKey $key, mixed $value): void
    {
        $this->handle([$key->value => $value]);
    }
}
