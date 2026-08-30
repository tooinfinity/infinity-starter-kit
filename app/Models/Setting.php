<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SettingKey;
use Carbon\CarbonInterface;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $id
 * @property-read SettingKey $key
 * @property-read mixed $value
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'key' => SettingKey::class,
            'value' => 'json',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
