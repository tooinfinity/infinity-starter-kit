<?php

declare(strict_types=1);

use Laravel\Chisel\PendingAnswers;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;

it('defines the default authentication and authorization feature selection', function (): void {
    /** @var Script $script */
    $script = require base_path('chisel.php');

    $questions = $script->questions();

    expect($questions)->toHaveCount(3)
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
        ])
        ->and($questions[1]->name)->toBe('authorization_features')
        ->and($questions[1]->options)->toBe([
            'roles-permissions' => 'Spatie Roles & Permissions (spatie/laravel-permission)',
        ])
        ->and($questions[1]->default)->toBe([
            'roles-permissions',
        ])
        /* @chisel-settings */
        ->and($questions[2]->name)->toBe('application_features')
        ->and($questions[2]->options)->toBe([
            'settings' => 'Application Settings',
            /* @chisel-user-management */
            'user-management' => 'User Management',
            /* @end-chisel-user-management */
            /* @chisel-localization */
            'localization' => 'Localization / Multi-language Support',
            /* @end-chisel-localization */
            /* @chisel-notifications */
            'notifications' => 'Notifications',
            /* @end-chisel-notifications */
            /* @chisel-audit-trails */
            'audit-trails' => 'Audit Trails',
            /* @end-chisel-audit-trails */
        ])
        ->and($questions[2]->default)->toBe([
            'settings',
            /* @chisel-user-management */
            'user-management',
            /* @end-chisel-user-management */
            /* @chisel-localization */
            'localization',
            /* @end-chisel-localization */
            /* @chisel-notifications */
            'notifications',
            /* @end-chisel-notifications */
            /* @chisel-audit-trails */
            'audit-trails',
            /* @end-chisel-audit-trails */
        ]);
    /* @end-chisel-settings */
});

it('registers the feature installer as a post-create command', function (): void {
    /** @var array{scripts: array{post-create-project-cmd: list<string>}} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['scripts']['post-create-project-cmd'])
        ->toContain('@php artisan install:features --ansi');
});

it('throws when answers option is invalid json', function (): void {
    $this->artisan('install:features', ['--answers' => '{invalid-json']);
})->throws(JsonException::class);

it('throws when answers option does not decode to an array', function (): void {
    $this->artisan('install:features', ['--answers' => '"just-a-string"']);
})->throws(RuntimeException::class, 'The --answers option must decode to a JSON object.');

it('executes chisel with provided answers in non-interactive mode', function (): void {
    $answers = [
        'auth_features' => ['registration'],
    ];

    $pendingAnswers = Mockery::mock(PendingAnswers::class);
    $pendingAnswers->shouldReceive('onQuestion')->once()->andReturnSelf();
    $pendingAnswers->shouldReceive('interactive')->with(false)->once()->andReturnSelf();
    $pendingAnswers->shouldReceive('withAnswers')->with($answers)->once()->andReturnSelf();

    $mockScript = Mockery::mock(Script::class);
    $mockScript->shouldReceive('collectAnswers')->once()->andReturn($pendingAnswers);
    $mockScript->shouldReceive('chisel')->with($pendingAnswers)->once();

    app()->instance(Script::class, $mockScript);

    $this->artisan('install:features', [
        '--answers' => json_encode($answers, JSON_THROW_ON_ERROR),
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('executes chisel without answers option defaulting to empty array', function (): void {
    $pendingAnswers = Mockery::mock(PendingAnswers::class);
    $pendingAnswers->shouldReceive('onQuestion')->once()->andReturnSelf();
    $pendingAnswers->shouldReceive('interactive')->with(false)->once()->andReturnSelf();
    $pendingAnswers->shouldReceive('withAnswers')->with([])->once()->andReturnSelf();

    $mockScript = Mockery::mock(Script::class);
    $mockScript->shouldReceive('collectAnswers')->once()->andReturn($pendingAnswers);
    $mockScript->shouldReceive('chisel')->with($pendingAnswers)->once();

    app()->instance(Script::class, $mockScript);

    $this->artisan('install:features', [
        '--no-interaction' => true,
    ])->assertSuccessful();
});

it('throws when question type is unsupported in question callback', function (): void {
    $capturedCallback = null;

    $pendingAnswers = Mockery::mock(PendingAnswers::class);
    $pendingAnswers->shouldReceive('onQuestion')
        ->once()
        ->andReturnUsing(function ($callback) use (&$capturedCallback, $pendingAnswers) {
            $capturedCallback = $callback;

            return $pendingAnswers;
        });
    $pendingAnswers->shouldReceive('interactive')->once()->andReturnSelf();
    $pendingAnswers->shouldReceive('withAnswers')->once()->andReturnSelf();

    $mockScript = Mockery::mock(Script::class);
    $mockScript->shouldReceive('collectAnswers')->once()->andReturn($pendingAnswers);
    $mockScript->shouldReceive('chisel')->once();

    app()->instance(Script::class, $mockScript);

    $this->artisan('install:features', [
        '--answers' => '{}',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($capturedCallback)->toBeCallable();

    $ref = new ReflectionClass(Question::class);
    /** @var Question $unsupportedQuestion */
    $unsupportedQuestion = $ref->newInstanceWithoutConstructor();
    $prop = $ref->getProperty('type');
    $prop->setValue($unsupportedQuestion, 'unsupported_type');

    expect(fn () => $capturedCallback($unsupportedQuestion))
        ->toThrow(RuntimeException::class, 'Unsupported question type [unsupported_type].');
});
