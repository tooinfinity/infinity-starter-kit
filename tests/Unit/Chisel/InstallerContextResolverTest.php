<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\InstallerContextResolver;
use App\Chisel\Installer\Preset;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

$cleanEnv = function (string ...$keys): void {
    foreach ($keys as $key) {
        putenv($key);
        unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
    }
};

$makeCommand = function (array $options = []): Command {
    $command = new #[Signature('test:resolve {--answers=} {--answers-file=} {--preset=} {--admin-name=} {--admin-email=} {--admin-password=}')]
    class extends Command {};
    $command->setInput(new ArrayInput($options, $command->getDefinition()));
    $command->setOutput(new OutputStyle(new ArrayInput($options, $command->getDefinition()), new BufferedOutput));

    return $command;
};

$makeScript = (fn (): Script => Chisel::script(base_path())
    ->questions([
        Question::multiselect('auth_features', 'Auth', [
            'login' => 'Login',
            'logout' => 'Logout',
            'registration' => 'Registration',
            'email-verification' => 'Email verification',
            'password-reset' => 'Password reset',
            'password-update' => 'Password update',
            'two-factor' => 'Two-factor authentication',
        ], ['registration']),
        Question::multiselect('optional_modules', 'Modules', [
            'authorization' => 'Authorization',
            'settings' => 'Settings',
            'user-management' => 'User Management',
            'localization' => 'Localization',
            'notifications' => 'Notifications',
            'audit-trails' => 'Audit Trails',
            'reporting' => 'Reporting',
        ], []),
    ]));

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

test('hasEnvironmentAnswersFile returns true for CHISEL_ANSWERS_FILE and LARAVEL_INSTALLER_ANSWERS_FILE', function () use ($cleanEnv): void {
    $cleanEnv('CHISEL_ANSWERS_FILE', 'LARAVEL_INSTALLER_ANSWERS_FILE');

    expect(InstallerContextResolver::hasEnvironmentAnswersFile())->toBeFalse();

    putenv('LARAVEL_INSTALLER_ANSWERS_FILE=/path/to/answers.json');
    $_ENV['LARAVEL_INSTALLER_ANSWERS_FILE'] = '/path/to/answers.json';
    $_SERVER['LARAVEL_INSTALLER_ANSWERS_FILE'] = '/path/to/answers.json';

    try {
        expect(InstallerContextResolver::hasEnvironmentAnswersFile())->toBeTrue()
            ->and(InstallerContextResolver::environmentAnswersFile())->toBe('/path/to/answers.json');

        putenv('CHISEL_ANSWERS_FILE=/path/to/chisel.json');
        $_ENV['CHISEL_ANSWERS_FILE'] = '/path/to/chisel.json';
        $_SERVER['CHISEL_ANSWERS_FILE'] = '/path/to/chisel.json';

        expect(InstallerContextResolver::environmentAnswersFile())->toBe('/path/to/chisel.json');
    } finally {
        $cleanEnv('CHISEL_ANSWERS_FILE', 'LARAVEL_INSTALLER_ANSWERS_FILE');
    }
});

test('hasEnvironmentPreset returns true for CHISEL_PRESET and LARAVEL_INSTALLER_PRESET', function () use ($cleanEnv): void {
    $cleanEnv('CHISEL_PRESET', 'LARAVEL_INSTALLER_PRESET');

    expect(InstallerContextResolver::hasEnvironmentPreset())->toBeFalse();

    putenv('LARAVEL_INSTALLER_PRESET=standard');
    $_ENV['LARAVEL_INSTALLER_PRESET'] = 'standard';
    $_SERVER['LARAVEL_INSTALLER_PRESET'] = 'standard';

    try {
        expect(InstallerContextResolver::hasEnvironmentPreset())->toBeTrue()
            ->and(InstallerContextResolver::environmentPreset())->toBe('standard');

        putenv('CHISEL_PRESET=minimal');
        $_ENV['CHISEL_PRESET'] = 'minimal';
        $_SERVER['CHISEL_PRESET'] = 'minimal';

        expect(InstallerContextResolver::environmentPreset())->toBe('minimal');
    } finally {
        $cleanEnv('CHISEL_PRESET', 'LARAVEL_INSTALLER_PRESET');
    }
});

test('resolve obtains answers from CHISEL_ANSWERS and LARAVEL_INSTALLER_ANSWERS environment variables', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ANSWERS', 'LARAVEL_INSTALLER_ANSWERS');

    $command = $makeCommand();
    $script = $makeScript();

    // Test CHISEL_ANSWERS
    try {
        putenv('CHISEL_ANSWERS={"auth_features":["registration"],"optional_modules":["authorization"]}');
        $_ENV['CHISEL_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["authorization"]}';
        $_SERVER['CHISEL_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["authorization"]}';

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
        expect($context->selectedModules)->toBe(['authorization'])
            ->and($context->hasAuthorization)->toBeTrue();
    } finally {
        $cleanEnv('CHISEL_ANSWERS');
    }

    // Test LARAVEL_INSTALLER_ANSWERS fallback
    putenv('LARAVEL_INSTALLER_ANSWERS={"auth_features":["registration"],"optional_modules":["settings","authorization"]}');
    $_ENV['LARAVEL_INSTALLER_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["settings","authorization"]}';
    $_SERVER['LARAVEL_INSTALLER_ANSWERS'] = '{"auth_features":["registration"],"optional_modules":["settings","authorization"]}';

    try {
        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
        expect($context->selectedModules)->toBe(['settings', 'authorization'])
            ->and($context->hasAuthorization)->toBeTrue();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_ANSWERS');
    }
});

test('resolve handles null and empty answers cleanly with default questions', function () use ($makeCommand, $makeScript): void {
    $command = $makeCommand();
    $script = $makeScript();

    $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
    expect($context->providedAnswers)->toBe([])
        ->and($context->selectedAuthFeatures)->toBe(['registration']);
});

test('resolve extracts admin credentials from answers flat fields and environment variables', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ADMIN_NAME', 'CHISEL_ADMIN_EMAIL', 'CHISEL_ADMIN_PASSWORD');

    try {
        putenv('CHISEL_ADMIN_NAME=EnvName');
        $_ENV['CHISEL_ADMIN_NAME'] = 'EnvName';
        putenv('CHISEL_ADMIN_EMAIL=env@example.com');
        $_ENV['CHISEL_ADMIN_EMAIL'] = 'env@example.com';
        putenv('CHISEL_ADMIN_PASSWORD=envpass');
        $_ENV['CHISEL_ADMIN_PASSWORD'] = 'envpass';

        $command = $makeCommand();
        $script = $makeScript();

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);
        expect($context->adminName)->toBe('EnvName')
            ->and($context->adminEmail)->toBe('env@example.com')
            ->and($context->adminPassword)->toBe('envpass');
    } finally {
        $cleanEnv('CHISEL_ADMIN_NAME', 'CHISEL_ADMIN_EMAIL', 'CHISEL_ADMIN_PASSWORD');
    }

    // Flat fields in providedAnswers
    $answers = json_encode([
        'admin_name' => 'FlatName',
        'admin_email' => 'flat@example.com',
        'admin_password' => 'flatpass',
    ], JSON_THROW_ON_ERROR);
    $commandWithAnswers = $makeCommand(['--answers' => $answers]);

    $contextFlat = InstallerContextResolver::resolve($commandWithAnswers, $script, isMockedScript: true, isInteractive: false);
    expect($contextFlat->adminName)->toBe('FlatName')
        ->and($contextFlat->adminEmail)->toBe('flat@example.com')
        ->and($contextFlat->adminPassword)->toBe('flatpass');
});

test('shouldDeferInstallerHooks returns true only when LARAVEL_INSTALLER_DEFER_HOOKS is true and no answers present', function () use ($cleanEnv, $makeCommand): void {
    $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS', 'CHISEL_ANSWERS', 'LARAVEL_INSTALLER_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');

    $commandWithoutAnswers = $makeCommand();

    expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeFalse();

    putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
    $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
    $_SERVER['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';

    try {
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeTrue();

        $commandWithAnswers = $makeCommand(['--answers' => '{}']);
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithAnswers))->toBeFalse();

        $commandWithFile = $makeCommand(['--answers-file' => 'test.json']);
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithFile))->toBeFalse();

        $commandWithPreset = $makeCommand(['--preset' => 'minimal']);
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithPreset))->toBeFalse();

        putenv('CHISEL_ANSWERS={}');
        $_ENV['CHISEL_ANSWERS'] = '{}';
        $_SERVER['CHISEL_ANSWERS'] = '{}';
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeFalse();
        $cleanEnv('CHISEL_ANSWERS');

        putenv('CHISEL_ANSWERS_FILE=answers.json');
        $_ENV['CHISEL_ANSWERS_FILE'] = 'answers.json';
        $_SERVER['CHISEL_ANSWERS_FILE'] = 'answers.json';
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeFalse();
        $cleanEnv('CHISEL_ANSWERS_FILE');

        putenv('CHISEL_PRESET=minimal');
        $_ENV['CHISEL_PRESET'] = 'minimal';
        $_SERVER['CHISEL_PRESET'] = 'minimal';
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeFalse();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS', 'CHISEL_ANSWERS', 'LARAVEL_INSTALLER_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');
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

test('resolve correctly normalizes ai_guidelines boolean values, aliases, and defaults', function (): void {
    $script = Chisel::script(base_path())
        ->questions([
            Question::multiselect('auth_features', 'Auth', ['registration' => 'Registration'], ['registration']),
            Question::multiselect('optional_modules', 'Modules', ['authorization' => 'Authorization'], []),
            Question::multiselect('ai_guidelines', 'AI', ['guidelines' => 'AI guidelines'], ['guidelines']),
        ]);

    // 1. Default when omitted
    $command1 = new #[Signature('test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}')]
    class extends Command {};
    $command1->setInput(new ArrayInput(['--answers' => '{"auth_features":["registration"]}'], $command1->getDefinition()));
    $command1->setOutput(new OutputStyle(new ArrayInput([], $command1->getDefinition()), new BufferedOutput));

    $context1 = InstallerContextResolver::resolve($command1, $script, isMockedScript: true, isInteractive: false);
    expect($context1->includeAiGuidelines)->toBeTrue()
        ->and($context1->answers['ai_guidelines'])->toBe(['guidelines']);

    // 2. Boolean false normalizes to empty array
    $command2 = new #[Signature('test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}')]
    class extends Command {};
    $command2->setInput(new ArrayInput(['--answers' => '{"ai_guidelines":false}'], $command2->getDefinition()));
    $command2->setOutput(new OutputStyle(new ArrayInput([], $command2->getDefinition()), new BufferedOutput));

    $context2 = InstallerContextResolver::resolve($command2, $script, isMockedScript: true, isInteractive: false);
    expect($context2->includeAiGuidelines)->toBeFalse()
        ->and($context2->answers['ai_guidelines'])->toBe([]);

    // 3. Boolean true normalizes to ['guidelines']
    $command3 = new #[Signature('test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}')]
    class extends Command {};
    $command3->setInput(new ArrayInput(['--answers' => '{"ai_guidelines":true}'], $command3->getDefinition()));
    $command3->setOutput(new OutputStyle(new ArrayInput([], $command3->getDefinition()), new BufferedOutput));

    $context3 = InstallerContextResolver::resolve($command3, $script, isMockedScript: true, isInteractive: false);
    expect($context3->includeAiGuidelines)->toBeTrue()
        ->and($context3->answers['ai_guidelines'])->toBe(['guidelines']);

    // 4. Alias ai_tooling normalized to ai_guidelines
    $command4 = new #[Signature('test:resolve {--answers=} {--admin-name=} {--admin-email=} {--admin-password=}')]
    class extends Command {};
    $command4->setInput(new ArrayInput(['--answers' => '{"ai_tooling":[]}'], $command4->getDefinition()));
    $command4->setOutput(new OutputStyle(new ArrayInput([], $command4->getDefinition()), new BufferedOutput));

    $context4 = InstallerContextResolver::resolve($command4, $script, isMockedScript: true, isInteractive: false);
    expect($context4->includeAiGuidelines)->toBeFalse()
        ->and($context4->answers['ai_guidelines'])->toBe([]);
});

test('loadAnswersFromFile loads valid JSON from absolute and relative paths', function (): void {
    $tempFile = sys_get_temp_dir().'/starter_kit_test_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, json_encode([
        'auth_features' => ['login', 'logout'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR));

    try {
        $answers = InstallerContextResolver::loadAnswersFromFile($tempFile);
        expect($answers)->toBe([
            'auth_features' => ['login', 'logout'],
            'optional_modules' => ['authorization'],
        ]);
    } finally {
        @unlink($tempFile);
    }
});

test('loadAnswersFromFile throws RuntimeException when file is unreadable', function (): void {
    if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
        $this->markTestSkipped('File permissions cannot reliably prevent reads on Windows or as root.');
    }

    $tempFile = sys_get_temp_dir().'/unreadable_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, '{}');

    try {
        chmod($tempFile, 0000);
        if (is_readable($tempFile)) {
            $this->markTestSkipped('File permissions do not prevent reads for this user.');
        }

        expect(fn (): array => InstallerContextResolver::loadAnswersFromFile($tempFile))
            ->toThrow(RuntimeException::class, "Answers file [{$tempFile}] cannot be read.");
    } finally {
        chmod($tempFile, 0666);
        @unlink($tempFile);
    }
});

test('loadAnswersFromFile throws RuntimeException when file path is empty', function (): void {
    expect(fn (): array => InstallerContextResolver::loadAnswersFromFile('   ', isOption: true))
        ->toThrow(RuntimeException::class, 'The --answers-file option must specify a non-empty file path.');

    expect(fn (): array => InstallerContextResolver::loadAnswersFromFile('', isOption: false))
        ->toThrow(RuntimeException::class, 'Answers file path cannot be empty.');
});

test('loadAnswersFromFile throws RuntimeException when file does not exist', function (): void {
    $nonExistent = sys_get_temp_dir().'/does_not_exist_'.bin2hex(random_bytes(6)).'.json';

    expect(fn (): array => InstallerContextResolver::loadAnswersFromFile($nonExistent))
        ->toThrow(RuntimeException::class, "Answers file [{$nonExistent}] does not exist.");
});

test('loadAnswersFromFile throws JsonException when file contains invalid JSON', function (): void {
    $tempFile = sys_get_temp_dir().'/invalid_json_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, '{not valid json');

    try {
        expect(fn (): array => InstallerContextResolver::loadAnswersFromFile($tempFile))
            ->toThrow(JsonException::class);
    } finally {
        @unlink($tempFile);
    }
});

test('loadAnswersFromFile throws RuntimeException when JSON is not an object', function (): void {
    $tempFile = sys_get_temp_dir().'/scalar_json_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, '"scalar value"');

    try {
        expect(fn (): array => InstallerContextResolver::loadAnswersFromFile($tempFile, isOption: true))
            ->toThrow(RuntimeException::class, 'The --answers-file option must decode to a JSON object.');

        expect(fn (): array => InstallerContextResolver::loadAnswersFromFile($tempFile, isOption: false))
            ->toThrow(RuntimeException::class, 'The answers file must decode to a JSON object.');
    } finally {
        @unlink($tempFile);
    }
});

test('resolve loads answers from --answers-file and marks context non-interactive', function () use ($makeCommand, $makeScript): void {
    $tempFile = sys_get_temp_dir().'/answers_opt_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, json_encode([
        'auth_features' => ['login', 'logout'],
        'optional_modules' => ['authorization'],
        'admin' => ['name' => 'FileAdmin', 'email' => 'admin@file.com', 'password' => 'secret123'],
    ], JSON_THROW_ON_ERROR));

    $command = $makeCommand(['--answers-file' => $tempFile]);
    $script = $makeScript();

    try {
        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: true);

        expect($context->isNonInteractive)->toBeTrue()
            ->and($context->selectedAuthFeatures)->toBe(['login', 'logout'])
            ->and($context->selectedModules)->toBe(['authorization'])
            ->and($context->adminName)->toBe('FileAdmin')
            ->and($context->adminEmail)->toBe('admin@file.com')
            ->and($context->adminPassword)->toBe('secret123');
    } finally {
        @unlink($tempFile);
    }
});

test('resolve loads answers from --preset and marks context non-interactive', function (string $presetName) use ($makeCommand, $makeScript): void {
    $command = $makeCommand(['--preset' => $presetName]);
    $script = $makeScript();

    $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: true);

    expect($context->isNonInteractive)->toBeTrue()
        ->and($context->selectedAuthFeatures)->toBe(Preset::answersFor($presetName)['auth_features'])
        ->and($context->selectedModules)->toBe(Preset::answersFor($presetName)['optional_modules']);
})->with([
    'minimal' => ['minimal'],
    'standard' => ['standard'],
    'full' => ['full'],
]);

test('resolve throws on invalid --preset value before mutating anything', function () use ($makeCommand, $makeScript): void {
    $command = $makeCommand(['--preset' => 'invalid-preset']);
    $script = $makeScript();

    expect(fn (): InstallerContext => InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: true))
        ->toThrow(InvalidArgumentException::class, 'Unknown preset [invalid-preset]. Available presets: minimal, standard, full.');
});

test('precedence: inline CLI answers take precedence over --answers-file, preset, and environment', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');

    $tempFile = sys_get_temp_dir().'/prec_file_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, json_encode([
        'auth_features' => ['login'],
        'optional_modules' => ['settings'],
    ], JSON_THROW_ON_ERROR));

    try {
        putenv('CHISEL_ANSWERS={"auth_features":["two-factor"],"optional_modules":["reporting"]}');
        $_ENV['CHISEL_ANSWERS'] = '{"auth_features":["two-factor"],"optional_modules":["reporting"]}';

        $command = $makeCommand([
            '--answers' => '{"auth_features":["login","logout"],"optional_modules":["authorization"]}',
            '--answers-file' => $tempFile,
            '--preset' => 'full',
        ]);
        $script = $makeScript();

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);

        expect($context->selectedAuthFeatures)->toBe(['login', 'logout'])
            ->and($context->selectedModules)->toBe(['authorization']);
    } finally {
        $cleanEnv('CHISEL_ANSWERS');
        @unlink($tempFile);
    }
});

test('precedence: --answers-file takes precedence over --preset and environment', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');

    $tempFile = sys_get_temp_dir().'/prec_file2_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, json_encode([
        'auth_features' => ['login', 'logout'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR));

    try {
        putenv('CHISEL_ANSWERS={"auth_features":["two-factor"],"optional_modules":["reporting"]}');
        $_ENV['CHISEL_ANSWERS'] = '{"auth_features":["two-factor"],"optional_modules":["reporting"]}';

        $command = $makeCommand([
            '--answers-file' => $tempFile,
            '--preset' => 'full',
        ]);
        $script = $makeScript();

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);

        expect($context->selectedAuthFeatures)->toBe(['login', 'logout'])
            ->and($context->selectedModules)->toBe(['authorization']);
    } finally {
        $cleanEnv('CHISEL_ANSWERS');
        @unlink($tempFile);
    }
});

test('precedence: CLI --preset takes precedence over environment answers', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');

    try {
        putenv('CHISEL_ANSWERS={"auth_features":["two-factor"],"optional_modules":["reporting"]}');
        $_ENV['CHISEL_ANSWERS'] = '{"auth_features":["two-factor"],"optional_modules":["reporting"]}';

        $command = $makeCommand([
            '--preset' => 'minimal',
        ]);
        $script = $makeScript();

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);

        expect($context->selectedAuthFeatures)->toBe(Preset::answersFor(Preset::MINIMAL)['auth_features'])
            ->and($context->selectedModules)->toBe(Preset::answersFor(Preset::MINIMAL)['optional_modules']);
    } finally {
        $cleanEnv('CHISEL_ANSWERS');
    }
});

test('precedence: environment answers take precedence over environment answers-file and environment preset', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');

    $tempFile = sys_get_temp_dir().'/prec_env_file_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['settings'],
    ], JSON_THROW_ON_ERROR));

    try {
        putenv('CHISEL_ANSWERS={"auth_features":["login","logout"],"optional_modules":["authorization"]}');
        $_ENV['CHISEL_ANSWERS'] = '{"auth_features":["login","logout"],"optional_modules":["authorization"]}';

        putenv('CHISEL_ANSWERS_FILE='.$tempFile);
        $_ENV['CHISEL_ANSWERS_FILE'] = $tempFile;

        putenv('CHISEL_PRESET=full');
        $_ENV['CHISEL_PRESET'] = 'full';

        $command = $makeCommand();
        $script = $makeScript();

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);

        expect($context->selectedAuthFeatures)->toBe(['login', 'logout'])
            ->and($context->selectedModules)->toBe(['authorization']);
    } finally {
        $cleanEnv('CHISEL_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');
        @unlink($tempFile);
    }
});

test('precedence: environment answers-file takes precedence over environment preset', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');

    $tempFile = sys_get_temp_dir().'/prec_env_file2_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, json_encode([
        'auth_features' => ['login', 'logout'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR));

    try {
        putenv('CHISEL_ANSWERS_FILE='.$tempFile);
        $_ENV['CHISEL_ANSWERS_FILE'] = $tempFile;

        putenv('CHISEL_PRESET=full');
        $_ENV['CHISEL_PRESET'] = 'full';

        $command = $makeCommand();
        $script = $makeScript();

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);

        expect($context->selectedAuthFeatures)->toBe(['login', 'logout'])
            ->and($context->selectedModules)->toBe(['authorization']);
    } finally {
        $cleanEnv('CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');
        @unlink($tempFile);
    }
});

test('precedence: environment preset applies when no higher precedence option is present', function () use ($cleanEnv, $makeCommand, $makeScript): void {
    $cleanEnv('CHISEL_ANSWERS', 'CHISEL_ANSWERS_FILE', 'CHISEL_PRESET');

    try {
        putenv('CHISEL_PRESET=minimal');
        $_ENV['CHISEL_PRESET'] = 'minimal';

        $command = $makeCommand();
        $script = $makeScript();

        $context = InstallerContextResolver::resolve($command, $script, isMockedScript: true, isInteractive: false);

        expect($context->selectedAuthFeatures)->toBe(Preset::answersFor(Preset::MINIMAL)['auth_features'])
            ->and($context->selectedModules)->toBe(Preset::answersFor(Preset::MINIMAL)['optional_modules']);
    } finally {
        $cleanEnv('CHISEL_PRESET');
    }
});
