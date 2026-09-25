<?php

declare(strict_types=1);

use App\Enums\Locale;

test('Locale enum has all expected cases', function (): void {
    $cases = Locale::cases();

    expect($cases)->toHaveCount(3)
        ->and(Locale::English->value)->toBe('en')
        ->and(Locale::French->value)->toBe('fr')
        ->and(Locale::Arabic->value)->toBe('ar');
});

test('Locale::values() returns all locale codes', function (): void {
    expect(Locale::values())->toBe(['en', 'fr', 'ar']);
});

test('Locale::name() returns English display names', function (): void {
    expect(Locale::English->name())->toBe('English')
        ->and(Locale::French->name())->toBe('French')
        ->and(Locale::Arabic->name())->toBe('Arabic');
});

test('Locale::nativeName() returns native display names', function (): void {
    expect(Locale::English->nativeName())->toBe('English')
        ->and(Locale::French->nativeName())->toBe('Français')
        ->and(Locale::Arabic->nativeName())->toBe('العربية');
});

test('Locale::direction() returns correct direction', function (): void {
    expect(Locale::English->direction())->toBe('ltr')
        ->and(Locale::French->direction())->toBe('ltr')
        ->and(Locale::Arabic->direction())->toBe('rtl');
});

test('Locale::isRtl() returns correct boolean', function (): void {
    expect(Locale::English->isRtl())->toBeFalse()
        ->and(Locale::French->isRtl())->toBeFalse()
        ->and(Locale::Arabic->isRtl())->toBeTrue();
});

test('Locale::default() returns English', function (): void {
    expect(Locale::default())->toBe(Locale::English);
});

test('Locale::default() respects config', function (): void {
    config(['app.locale' => 'fr']);

    expect(Locale::default())->toBe(Locale::French);
});

test('Locale::default() falls back to English for invalid config', function (): void {
    config(['app.locale' => 'invalid']);

    expect(Locale::default())->toBe(Locale::English);
});

test('Locale::default() falls back to English when config is not a string', function (): void {
    config(['app.locale' => null]);

    expect(Locale::default())->toBe(Locale::English);
});

test('Locale::toArray() returns correct shape', function (): void {
    expect(Locale::English->toArray())->toBe([
        'code' => 'en',
        'name' => 'English',
        'nativeName' => 'English',
        'direction' => 'ltr',
    ]);
});

test('Locale::toOptions() returns all locales as arrays', function (): void {
    $options = Locale::toOptions();

    expect($options)->toHaveCount(3)
        ->and($options[0]['code'])->toBe('en')
        ->and($options[1]['code'])->toBe('fr')
        ->and($options[2]['code'])->toBe('ar');
});

test('Locale can be created from valid string', function (): void {
    expect(Locale::tryFrom('en'))->toBe(Locale::English)
        ->and(Locale::tryFrom('fr'))->toBe(Locale::French)
        ->and(Locale::tryFrom('ar'))->toBe(Locale::Arabic);
});

test('Locale returns null for invalid string', function (): void {
    expect(Locale::tryFrom('invalid'))->toBeNull()
        ->and(Locale::tryFrom(''))->toBeNull();
});
