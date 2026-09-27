<?php

declare(strict_types=1);

$cleanEnv = function (string $key): void {
    putenv($key);
    unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
};

test('command fails when --answers option does not decode to a JSON object', function (): void {
    $this->artisan('install:features', ['--answers' => 'invalid-json'])
        ->assertFailed();
})->throws(JsonException::class);

test('command fails when --answers option decodes to non-array scalar', function (): void {
    $this->artisan('install:features', ['--answers' => '"just a string"'])
        ->assertFailed();
})->throws(RuntimeException::class, 'The --answers option must decode to a JSON object.');

test('command defers execution when LARAVEL_INSTALLER_DEFER_HOOKS is true and no answers given', function () use ($cleanEnv): void {
    putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
    $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
    $_SERVER['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';

    try {
        $this->artisan('install:features')
            ->assertSuccessful();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS');
    }
});

test('command rejects invalid dependency combination passed via answers', function () use ($cleanEnv): void {
    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    try {
        $answers = json_encode([
            'auth_features' => ['registration'],
            'optional_modules' => ['user-management'],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers]);
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    }
})->throws(RuntimeException::class, 'The "user-management" module requires the following module(s): authorization.');
