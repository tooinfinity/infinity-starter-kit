<?php

declare(strict_types=1);

use Laravel\Chisel\Script;

it('defines the default authentication feature selection', function (): void {
    /** @var Script $script */
    $script = require base_path('chisel.php');

    $questions = $script->questions();

    expect($questions)->toHaveCount(1)
        ->and($questions[0]->name)->toBe('auth_features')
        ->and($questions[0]->options)->toBe([
            'registration' => 'Registration',
            'email-verification' => 'Email verification',
            'two-factor-authentication' => 'Two-factor authentication',
        ])
        ->and($questions[0]->default)->toBe([
            'registration',
            'email-verification',
            'two-factor-authentication',
        ]);
});

it('registers the feature installer as a post-create command', function (): void {
    /** @var array{scripts: array{post-create-project-cmd: list<string>}} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['scripts']['post-create-project-cmd'])
        ->toContain('@php artisan install:features --ansi');
});
