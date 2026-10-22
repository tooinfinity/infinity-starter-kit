<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rules\Password;

it('configures default password rules according to environment', function (): void {
    App::detectEnvironment(fn (): string => 'local');
    $localRule = Password::defaults();
    expect($localRule)->toBeInstanceOf(Password::class);

    App::detectEnvironment(fn (): string => 'production');
    $prodRule = Password::defaults();
    expect($prodRule)->toBeInstanceOf(Password::class);

    App::detectEnvironment(fn (): string => 'testing');
});
