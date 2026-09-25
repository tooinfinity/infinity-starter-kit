<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

test('reporting localization files exist for en, fr, and ar', function (): void {
    expect(file_exists(base_path('lang/en/reports.php')))->toBeTrue();
    expect(file_exists(base_path('lang/fr/reports.php')))->toBeTrue();
    expect(file_exists(base_path('lang/ar/reports.php')))->toBeTrue();
});

test('fr and ar have all keys present in en reporting translations', function (): void {
    /** @var array<string, mixed> $en */
    $en = require base_path('lang/en/reports.php');
    /** @var array<string, mixed> $fr */
    $fr = require base_path('lang/fr/reports.php');
    /** @var array<string, mixed> $ar */
    $ar = require base_path('lang/ar/reports.php');

    $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
        $result = [];
        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $result = array_merge($result, $flatten($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    };

    $enKeys = array_keys($flatten($en));
    $frKeys = array_keys($flatten($fr));
    $arKeys = array_keys($flatten($ar));

    expect($frKeys)->toEqualCanonicalizing($enKeys);
    expect($arKeys)->toEqualCanonicalizing($enKeys);
});

test('translations can be retrieved in multiple locales', function (): void {
    App::setLocale('en');
    expect(__('reports.title'))->toBe('Reports');

    App::setLocale('fr');
    expect(__('reports.title'))->toBe('Rapports');

    App::setLocale('ar');
    expect(__('reports.title'))->toBe('التقارير');
});
