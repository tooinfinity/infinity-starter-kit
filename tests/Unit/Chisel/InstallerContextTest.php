<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallerContext;

test('installer context holds strongly-typed lifecycle properties', function (): void {
    $context = new InstallerContext(
        providedAnswers: ['auth_features' => ['login', 'registration']],
        answers: ['auth_features' => ['login', 'registration'], 'optional_modules' => ['authorization']],
        paths: ['auth' => []],
        selectedModules: ['authorization'],
        selectedAuthFeatures: ['login', 'registration'],
        hasAuthorization: true,
        adminName: 'Admin',
        adminEmail: 'admin@example.com',
        isNonInteractive: true,
        skipNode: false,
        isMockedScript: true,
    );

    expect($context->providedAnswers)->toBe(['auth_features' => ['login', 'registration']])
        ->and($context->answers)->toHaveKeys(['auth_features', 'optional_modules'])
        ->and($context->paths)->toBe(['auth' => []])
        ->and($context->selectedModules)->toBe(['authorization'])
        ->and($context->selectedAuthFeatures)->toBe(['login', 'registration'])
        ->and($context->hasAuthorization)->toBeTrue()
        ->and($context->adminName)->toBe('Admin')
        ->and($context->adminEmail)->toBe('admin@example.com')
        ->and($context->isNonInteractive)->toBeTrue()
        ->and($context->skipNode)->toBeFalse()
        ->and($context->isMockedScript)->toBeTrue()
        ->and($context->includeAiGuidelines)->toBeTrue();
});

test('installer context redacts admin secrets from provided answers in messages', function (): void {
    $context = new InstallerContext(
        providedAnswers: [
            'admin' => ['password' => 'nested-secret'],
            'admin_password' => 'flat-secret',
        ],
        answers: [],
        paths: [],
        selectedModules: [],
        selectedAuthFeatures: [],
        hasAuthorization: false,
        adminName: null,
        adminEmail: null,
        isNonInteractive: true,
        skipNode: false,
        isMockedScript: false,
    );

    $redacted = $context->redact('Error: flat-secret failed with nested-secret');
    expect($redacted)->toBe('Error: [REDACTED] failed with [REDACTED]');
});
