<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallerContextResolver;
use Illuminate\Console\Command;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

$cleanEnv = function (string ...$keys): void {
    foreach ($keys as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
};

test('hasEnvironmentAnswers returns true for LARAVEL_INSTALLER_ANSWERS when CHISEL_ANSWERS is missing', function () use ($cleanEnv): void {
    $cleanEnv('CHISEL_ANSWERS', 'LARAVEL_INSTALLER_ANSWERS');

    expect(InstallerContextResolver::hasEnvironmentAnswers())->toBeFalse();

    putenv('LARAVEL_INSTALLER_ANSWERS={"auth_features":["registration"]}');
    $_ENV['LARAVEL_INSTALLER_ANSWERS'] = '{"auth_features":["registration"]}';
    $_SERVER['LARAVEL_INSTALLER_ANSWERS'] = '{"auth_features":["registration"]}';

    try {
        expect(InstallerContextResolver::hasEnvironmentAnswers())->toBeTrue();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_ANSWERS');
    }
});

test('resolve obtains answers from CHISEL_ANSWERS and LARAVEL_INSTALLER_ANSWERS environment variables', function () use ($cleanEnv): void {
    $cleanEnv('CHISEL_ANSWERS', 'LARAVEL_INSTALLER_ANSWERS');

    $command = new class extends Command
    {
        protected $signature = 'test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}';
    };
    $command->setInput(new ArrayInput([], $command->getDefinition()));
    $command->setOutput(new Illuminate\Console\OutputStyle(new ArrayInput([], $command->getDefinition()), new BufferedOutput));

    $script = Chisel::script(base_path())
        ->questions([
            Question::multiselect('auth_features', 'Auth', ['registration' => 'Registration'], ['registration']),
            Question::multiselect('optional_modules', 'Modules', [
                'authorization' => 'Authorization',
                'settings' => 'Settings',
            ], []),
        ]);

    // Test CHISEL_ANSWERS
    putenv('CHISEL_ANSWERS={"auth_features":["registration"],"optional_modules":["authorization"]}');
    $_ENV['CHISEL_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["authorization"]}';
    $_SERVER['CHISEL_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["authorization"]}';

    try {
        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
        expect($context->selectedModules)->toBe(['authorization'])
            ->and($context->hasAuthorization)->toBeTrue();
    } finally {
        $cleanEnv('CHISEL_ANSWERS');
    }

    // Test LARAVEL_INSTALLER_ANSWERS fallback
    putenv('LARAVEL_INSTALLER_ANSWERS={"auth_features":["registration"],"optional_modules":["settings"]}');
    $_ENV['LARAVEL_INSTALLER_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["settings"]}';
    $_SERVER['LARAVEL_INSTALLER_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["settings"]}';

    try {
        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
        expect($context->selectedModules)->toBe(['settings'])
            ->and($context->hasAuthorization)->toBeFalse();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_ANSWERS');
    }
});

test('resolve handles null and empty answers cleanly with default questions', function (): void {
    $command = new class extends Command
    {
        protected $signature = 'test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}';
    };
    $command->setInput(new ArrayInput([], $command->getDefinition()));
    $command->setOutput(new Illuminate\Console\OutputStyle(new ArrayInput([], $command->getDefinition()), new BufferedOutput));

    $script = Chisel::script(base_path())
        ->questions([
            Question::multiselect('auth_features', 'Auth', ['registration' => 'Registration'], ['registration']),
            Question::multiselect('optional_modules', 'Modules', ['authorization' => 'Authorization'], []),
        ]);

    $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
    expect($context->providedAnswers)->toBe([])
        ->and($context->selectedAuthFeatures)->toBe(['registration']);
});

test('resolve extracts admin credentials from answers flat fields and environment variables', function () use ($cleanEnv): void {
    $cleanEnv('CHISEL_ADMIN_NAME', 'CHISEL_ADMIN_EMAIL', 'CHISEL_ADMIN_PASSWORD');

    putenv('CHISEL_ADMIN_NAME=EnvName');
    $_ENV['CHISEL_ADMIN_NAME'] = 'EnvName';
    putenv('CHISEL_ADMIN_EMAIL=env@example.com');
    $_ENV['CHISEL_ADMIN_EMAIL'] = 'env@example.com';
    putenv('CHISEL_ADMIN_PASSWORD=envpass');
    $_ENV['CHISEL_ADMIN_PASSWORD'] = 'envpass';

    $command = new class extends Command
    {
        protected $signature = 'test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}';
    };
    $command->setInput(new ArrayInput([], $command->getDefinition()));
    $command->setOutput(new Illuminate\Console\OutputStyle(new ArrayInput([], $command->getDefinition()), new BufferedOutput));

    $script = Chisel::script(base_path())
        ->questions([
            Question::multiselect('auth_features', 'Auth', ['registration' => 'Registration'], ['registration']),
            Question::multiselect('optional_modules', 'Modules', ['authorization' => 'Authorization'], []),
        ]);

    try {
        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
        expect($context->adminName)->toBe('EnvName')
            ->and($context->adminEmail)->toBe('env@example.com')
            ->and($context->adminPassword)->toBe('envpass');
    } finally {
        $cleanEnv('CHISEL_ADMIN_NAME', 'CHISEL_ADMIN_EMAIL', 'CHISEL_ADMIN_PASSWORD');
    }

    // Flat fields in providedAnswers
    $commandWithAnswers = new class extends Command
    {
        protected $signature = 'test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}';
    };
    $answers = json_encode([
        'admin_name' => 'FlatName',
        'admin_email' => 'flat@example.com',
        'admin_password' => 'flatpass',
    ], JSON_THROW_ON_ERROR);
    $commandWithAnswers->setInput(new ArrayInput(['--answers' => $answers], $commandWithAnswers->getDefinition()));
    $commandWithAnswers->setOutput(new Illuminate\Console\OutputStyle(new ArrayInput([], $commandWithAnswers->getDefinition()), new BufferedOutput));

    $contextFlat = InstallerContextResolver::resolve($commandWithAnswers, $script, isMockedScript: true, isInteractive: false);
    expect($contextFlat->adminName)->toBe('FlatName')
        ->and($contextFlat->adminEmail)->toBe('flat@example.com')
        ->and($contextFlat->adminPassword)->toBe('flatpass');
});

test('shouldDeferInstallerHooks returns true only when LARAVEL_INSTALLER_DEFER_HOOKS is true and no answers present', function () use ($cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS', 'CHISEL_ANSWERS', 'LARAVEL_INSTALLER_ANSWERS');

    $commandWithoutAnswers = new class extends Command
    {
        protected $signature = 'test:defer {--answers=}';
    };
    $commandWithoutAnswers->setInput(new ArrayInput([], $commandWithoutAnswers->getDefinition()));

    expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeFalse();

    putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
    $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
    $_SERVER['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';

    try {
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeTrue();

        $commandWithOption = new class extends Command
        {
            protected $signature = 'test:defer {--answers=}';
        };
        $commandWithOption->setInput(new ArrayInput(['--answers' => '{}'], $commandWithOption->getDefinition()));
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithOption))->toBeFalse();

        putenv('CHISEL_ANSWERS={}');
        $_ENV['CHISEL_ANSWERS'] = '{}';
        $_SERVER['CHISEL_ANSWERS'] = '{}';
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeFalse();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS', 'CHISEL_ANSWERS', 'LARAVEL_INSTALLER_ANSWERS');
    }
});

test('shouldSkipNode returns true when LARAVEL_INSTALLER_NO_NODE is enabled', function () use ($cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    expect(InstallerContextResolver::shouldSkipNode())->toBeFalse();

    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';
    $_SERVER['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    try {
        expect(InstallerContextResolver::shouldSkipNode())->toBeTrue();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    }
});
