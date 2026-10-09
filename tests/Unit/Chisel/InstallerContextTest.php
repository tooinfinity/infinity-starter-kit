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

test('installer context redacts secrets from CHISEL_ADMIN_PASSWORD env and flat admin_password answer', function (): void {
    putenv('CHISEL_ADMIN_PASSWORD=env-super-secret');
    $_ENV['CHISEL_ADMIN_PASSWORD'] = 'env-super-secret';

    try {
        $context = new InstallerContext(
            providedAnswers: ['admin_password' => 'flat-secret'],
            answers: [],
            paths: [],
            selectedModules: [],
            selectedAuthFeatures: [],
            hasAuthorization: false,
            adminName: null,
            adminEmail: null,
            adminPassword: null,
            isNonInteractive: true,
            skipNode: false,
            isMockedScript: false,
        );

        $redacted = $context->redact('Failed env-super-secret and flat-secret');
        expect($redacted)->toBe('Failed [REDACTED] and [REDACTED]');
    } finally {
        putenv('CHISEL_ADMIN_PASSWORD');
        unset($GLOBALS['_ENV']['CHISEL_ADMIN_PASSWORD'], $GLOBALS['_SERVER']['CHISEL_ADMIN_PASSWORD']);
    }
});
