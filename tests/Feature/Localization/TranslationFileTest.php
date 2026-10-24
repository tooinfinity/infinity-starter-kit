<?php

declare(strict_types=1);

use App\Enums\Locale;

test('all required translation files exist for English', function (): void {
    expect(file_exists(lang_path('en/common.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/localization.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/auth.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/passwords.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/pagination.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/validation.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/audit.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/notifications.php')))->toBeTrue()
        ->and(file_exists(lang_path('en/reports.php')))->toBeTrue()
        ->and(file_exists(lang_path('en.json')))->toBeTrue();
});

test('all required translation files exist for French', function (): void {
    expect(file_exists(lang_path('fr/common.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/localization.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/auth.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/passwords.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/pagination.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/validation.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/audit.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/notifications.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr/reports.php')))->toBeTrue()
        ->and(file_exists(lang_path('fr.json')))->toBeTrue();
});

test('all required translation files exist for Arabic', function (): void {
    expect(file_exists(lang_path('ar/common.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/localization.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/auth.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/passwords.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/pagination.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/validation.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/audit.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/notifications.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar/reports.php')))->toBeTrue()
        ->and(file_exists(lang_path('ar.json')))->toBeTrue();
});

test('all supported locales have all PHP translation keys matching English', function (string $file): void {
    $enKeys = array_keys_recursive(require lang_path("en/{$file}"));

    foreach (Locale::cases() as $locale) {
        $localeKeys = array_keys_recursive(require lang_path("{$locale->value}/{$file}"));
        expect($localeKeys)->toBe($enKeys, "Key mismatch in {$locale->value}/{$file}");
    }
})->with([
    'common.php',
    'localization.php',
    'auth.php',
    'passwords.php',
    'pagination.php',
    'validation.php',
    'audit.php',
    'notifications.php',
    'reports.php',
]);

test('all supported locales have matching JSON translation keys', function (): void {
    /** @var array<string, string> $enJson */
    $enJson = json_decode((string) file_get_contents(lang_path('en.json')), true);

    foreach (Locale::cases() as $locale) {
        /** @var array<string, string> $localeJson */
        $localeJson = json_decode((string) file_get_contents(lang_path("{$locale->value}.json")), true);

        $missingKeys = array_diff_key($enJson, $localeJson);
        $extraKeys = array_diff_key($localeJson, $enJson);

        expect($missingKeys)->toBeEmpty("Missing keys in {$locale->value}.json")
            ->and($extraKeys)->toBeEmpty("Extra keys in {$locale->value}.json");
    }
});

test('backend translations resolve for each locale', function (): void {
    foreach (Locale::cases() as $locale) {
        app()->setLocale($locale->value);

        $translated = __('localization.language');

        expect($translated)->not->toBe('localization.language', "Translation missing for {$locale->value}");
    }
});

test('validation translation resolves for each locale', function (): void {
    foreach (Locale::cases() as $locale) {
        app()->setLocale($locale->value);

        $translated = __('validation.required', ['attribute' => 'email']);

        expect($translated)->not->toBe('validation.required', "Validation translation missing for {$locale->value}")
            ->and($translated)->toContain('email');
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
 * @param  array<string, mixed>  $array
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
