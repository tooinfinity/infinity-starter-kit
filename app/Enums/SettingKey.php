<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKey: string
{
    case ApplicationName = 'application.name';
    case ApplicationTimezone = 'application.timezone';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::ApplicationName => config('app.name', 'Laravel'),
            self::ApplicationTimezone => config('app.timezone', 'UTC'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ApplicationName => 'Application Name',
            self::ApplicationTimezone => 'Timezone',
        };
    }

    public function group(): SettingGroup
    {
        return match ($this) {
            self::ApplicationName, self::ApplicationTimezone => SettingGroup::Application,
        };
    }

    /**
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return match ($this) {
            self::ApplicationName => ['required', 'string', 'max:255'],
            self::ApplicationTimezone => ['required', 'string', 'timezone:all'],
        };
    }
}
