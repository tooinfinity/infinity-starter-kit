<?php

declare(strict_types=1);

use App\Enums\Locale;

test('all required translation files exist for English', function (): void {
    expect(file_exists(lang_path('en/common.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/localization.php')))->toBeTrue();
});

test('all required translation files exist for French', function (): void {
    expect(file_exists(lang_path('fr/common.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/localization.php')))->toBeTrue();
});

test('all required translation files exist for Arabic', function (): void {
    expect(file_exists(lang_path('ar/common.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/localization.php')))->toBeTrue();
});

test('all supported locales have common translation keys', function (): void {
    $enKeys = array_keys_recursive(require lang_path('en/common.php'));

    foreach (Locale::cases() as $locale) {
        $localeKeys = array_keys_recursive(require lang_path("{$locale->value}/common.php"));
        expect($localeKeys)->toBe($enKeys, "Missing keys in {$locale->value}/common.php");
    }
});

test('all supported locales have localization translation keys', function (): void {
    $enKeys = array_keys_recursive(require lang_path('en/localization.php'));

    foreach (Locale::cases() as $locale) {
        $localeKeys = array_keys_recursive(require lang_path("{$locale->value}/localization.php"));
        expect($localeKeys)->toBe($enKeys, "Missing keys in {$locale->value}/localization.php");
    }
});

test('backend translations resolve for each locale', function (): void {
    foreach (Locale::cases() as $locale) {
        app()->setLocale($locale->value);

        $translated = __('localization.language');

        expect($translated)->not->toBe('localization.language', "Translation missing for {$locale->value}");
    }
});

test('translation placeholders resolve', function (): void {
    app()->setLocale('en');

    $translated = __('localization.current_language', ['language' => 'English']);

    expect($translated)->toBe('Current language: English');
});

test('translation directory is under lang/ not resources/lang/', function (): void {
    expect(lang_path())->toBe(base_path('lang'));
});

/**
 * @return list<string>
 */
function array_keys_recursive(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

        if (is_array($value)) {
            $keys = [...$keys, ...array_keys_recursive($value, $fullKey)];
        } else {
            $keys[] = $fullKey;
        }
    }

    sort($keys);

    return $keys;
}
