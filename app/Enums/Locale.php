<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case French = 'fr';
    case Arabic = 'ar';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        $configLocale = config('app.locale');

        if (is_string($configLocale)) {
            return self::tryFrom($configLocale) ?? self::English;
        }

        return self::English;
    }

    /**
     * @return list<array{code: string, name: string, nativeName: string, direction: string}>
     */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $locale): array => $locale->toArray(),
            self::cases(),
        );
    }

    public function name(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'French',
            self::Arabic => 'Arabic',
        };
    }

    public function nativeName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::French => 'Français',
            self::Arabic => 'العربية',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::English, self::French => 'ltr',
            self::Arabic => 'rtl',
        };
    }

    public function isRtl(): bool
    {
        return $this->direction() === 'rtl';
    }

    /**
     * @return array{code: string, name: string, nativeName: string, direction: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->value,
            'name' => $this->name(),
            'nativeName' => $this->nativeName(),
            'direction' => $this->direction(),
        ];
    }
}
