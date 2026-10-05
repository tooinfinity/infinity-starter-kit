<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallerContext;

test('installer context holds strongly-typed lifecycle properties', function (): void {
    $context = new InstallerContext(
        providedAnswers: ['auth_features' => ['registration']],
        answers: ['auth_features' => ['registration'], 'optional_modules' => ['authorization']],
        paths: ['auth' => []],
        selectedModules: ['authorization'],
        selectedAuthFeatures: ['registration'],
        hasAuthorization: true,
        adminName: 'Admin',
        adminEmail: 'admin@example.com',
        adminPassword: 'secret-password',
        isNonInteractive: true,
        skipNode: false,
        isMockedScript: true,
    );

    expect($context->providedAnswers)->toBe(['auth_features' => ['registration']])
        ->and($context->answers)->toHaveKeys(['auth_features', 'optional_modules'])
        ->and($context->paths)->toBe(['auth' => []])
        ->and($context->selectedModules)->toBe(['authorization'])
        ->and($context->selectedAuthFeatures)->toBe(['registration'])
        ->and($context->hasAuthorization)->toBeTrue()
        ->and($context->adminName)->toBe('Admin')
        ->and($context->adminEmail)->toBe('admin@example.com')
        ->and($context->adminPassword)->toBe('secret-password')
        ->and($context->isNonInteractive)->toBeTrue()
        ->and($context->skipNode)->toBeFalse()
        ->and($context->isMockedScript)->toBeTrue();
});

test('installer context redacts admin secrets from messages', function (): void {
    $context = new InstallerContext(
        providedAnswers: ['admin' => ['password' => 'inner-secret']],
        answers: [],
        paths: [],
        selectedModules: [],
        selectedAuthFeatures: [],
        hasAuthorization: false,
        adminName: null,
        adminEmail: null,
        adminPassword: 'direct-secret',
        isNonInteractive: true,
        skipNode: false,
        isMockedScript: false,
    );

    $redacted = $context->redact('Error: direct-secret failed with inner-secret');
    expect($redacted)->toBe('Error: [REDACTED] failed with [REDACTED]');
});
