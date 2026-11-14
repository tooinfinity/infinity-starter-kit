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
        if ($settings === []) {
            return;
        }

        $changed = DB::transaction(function () use ($settings): bool {
            $existingSettings = Setting::query()
                ->whereIn('key', array_keys($settings))
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Setting $setting): string => $setting->key->value);

            $shouldInvalidateCache = false;

            foreach ($settings as $key => $value) {
                $existing = $existingSettings->get($key);

                if ($existing !== null && $existing->value === $value) {
                    continue;
                }

                /* @chisel-audit-trails */
                $oldValue = $existing?->value;
                /* @end-chisel-audit-trails */

                if ($existing !== null) {
                    $existing->update(['value' => $value]);
                    /* @chisel-audit-trails */
                    $setting = $existing;
                    /* @end-chisel-audit-trails */
                    $shouldInvalidateCache = true;
                } else {
                    $createdSetting = Setting::query()->createOrFirst(
                        ['key' => $key],
                        ['value' => $value],
                    );

                    /* @chisel-audit-trails */
                    $setting = $createdSetting;
                    /* @end-chisel-audit-trails */

                    $shouldInvalidateCache = true;

                    if (! $createdSetting->wasRecentlyCreated) {
                        $reloadedSetting = Setting::query()
                            ->lockForUpdate()
                            ->findOrFail($createdSetting->id);

                        if ($reloadedSetting->value === $value) {
                            continue;
                        }

                        /* @chisel-audit-trails */
                        $oldValue = $reloadedSetting->value;
                        /* @end-chisel-audit-trails */

                        $reloadedSetting->update(['value' => $value]);

                        /* @chisel-audit-trails */
                        $setting = $reloadedSetting;
                        /* @end-chisel-audit-trails */
                    }
                }

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

            return $shouldInvalidateCache;
        });

        if ($changed) {
            Cache::forget('settings.all');
        }
    }

    public function set(SettingKey $key, mixed $value): void
    {
        $this->handle([$key->value => $value]);
    }
}
