<?php

declare(strict_types=1);

use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\InstallerContextResolver;
use App\Chisel\Installer\InstallerState;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\File;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

$cleanEnv = function (string ...$keys): void {
    foreach ($keys as $key) {
        putenv($key);
        unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
    }
};

$makeCommand = function (array $options = []): Command {
    $command = new #[Signature('test:resolve {--answers=} {--admin-name=} {--admin-email=} {--no-interaction}')]
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

test('shouldDeferInstallerHooks returns false when --answers is provided', function () use ($makeCommand, $cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS');

    $commandWithoutAnswers = $makeCommand([]);
    expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeFalse();

    putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
    $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
    try {
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithoutAnswers))->toBeTrue();

        $commandWithAnswers = $makeCommand(['--answers' => '{"auth_features":[],"optional_modules":[]}']);
        expect(InstallerContextResolver::shouldDeferInstallerHooks($commandWithAnswers))->toBeFalse();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS');
    }
});

test('shouldSkipNode reflects LARAVEL_INSTALLER_NO_NODE flag', function () use ($cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    expect(InstallerContextResolver::shouldSkipNode())->toBeFalse();

    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';
    try {
        expect(InstallerContextResolver::shouldSkipNode())->toBeTrue();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    }
});

test('installerFlag parses boolean strings correctly', function () use ($cleanEnv): void {
    $cleanEnv('TEST_FLAG');

    expect(InstallerContextResolver::installerFlag('TEST_FLAG'))->toBeFalse();

    foreach (['true', '1', 'on', 'yes'] as $trueVal) {
        putenv("TEST_FLAG={$trueVal}");
        $_ENV['TEST_FLAG'] = $trueVal;
        expect(InstallerContextResolver::installerFlag('TEST_FLAG'))->toBeTrue();
    }

    foreach (['false', '0', 'off', 'no', ''] as $falseVal) {
        putenv("TEST_FLAG={$falseVal}");
        $_ENV['TEST_FLAG'] = $falseVal;
        expect(InstallerContextResolver::installerFlag('TEST_FLAG'))->toBeFalse();
    }

    $cleanEnv('TEST_FLAG');
});

test('resolve loads valid answers from --answers in non-interactive mode', function () use ($makeCommand, $makeScript): void {
    $answers = [
        'auth_features' => ['login', 'logout'],
        'optional_modules' => ['authorization', 'settings'],
    ];

    $command = $makeCommand(['--answers' => json_encode($answers), '--no-interaction' => true]);
    $context = InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false);

    expect($context)->toBeInstanceOf(InstallerContext::class)
        ->and($context->isNonInteractive)->toBeTrue()
        ->and($context->selectedAuthFeatures)->toBe(['login', 'logout'])
        ->and($context->selectedModules)->toBe(['authorization', 'settings'])
        ->and($context->hasAuthorization)->toBeTrue();
});

test('resolve throws RuntimeException when non-interactive without --answers', function () use ($makeCommand, $makeScript): void {
    $command = $makeCommand(['--no-interaction' => true]);

    expect(fn (): InstallerContext => InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false))
        ->toThrow(RuntimeException::class, 'The --answers option is required when running non-interactively.');
});

test('resolve throws JsonException for malformed --answers JSON string', function () use ($makeCommand, $makeScript): void {
    $command = $makeCommand(['--answers' => '{invalid-json', '--no-interaction' => true]);

    expect(fn (): InstallerContext => InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false))
        ->toThrow(JsonException::class);
});

test('resolve throws RuntimeException for non-object --answers JSON', function (string $json) use ($makeCommand, $makeScript): void {
    $command = $makeCommand(['--answers' => $json, '--no-interaction' => true]);

    expect(fn (): InstallerContext => InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false))
        ->toThrow(RuntimeException::class, 'The --answers option must decode to a JSON object.');
})->with([
    'string' => ['"string value"'],
    'integer' => ['123'],
    'boolean' => ['true'],
    'empty string' => ['   '],
]);

test('resolve rejects passwords supplied in --answers for security reasons', function (array $answers) use ($makeCommand, $makeScript): void {
    $command = $makeCommand(['--answers' => json_encode($answers), '--no-interaction' => true]);

    expect(fn (): InstallerContext => InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false))
        ->toThrow(RuntimeException::class, 'Administrator passwords cannot be supplied via --answers for security reasons.');
})->with([
    'direct password' => [['auth_features' => [], 'optional_modules' => [], 'password' => 'secret']],
    'admin_password' => [['auth_features' => [], 'optional_modules' => [], 'admin_password' => 'secret']],
    'nested admin password' => [['auth_features' => [], 'optional_modules' => [], 'admin' => ['password' => 'secret']]],
]);

test('resolve throws RuntimeException when required answers keys are missing in non-interactive mode', function (array $answers, string $expectedMessage) use ($makeCommand, $makeScript): void {
    $command = $makeCommand(['--answers' => json_encode($answers), '--no-interaction' => true]);

    expect(fn (): InstallerContext => InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false))
        ->toThrow(RuntimeException::class, $expectedMessage);
})->with([
    'missing auth_features' => [
        ['optional_modules' => []],
        "The 'auth_features' key is required in --answers in non-interactive mode.",
    ],
    'non-array auth_features' => [
        ['auth_features' => 'not-an-array', 'optional_modules' => []],
        "The 'auth_features' option must be an array.",
    ],
    'missing optional_modules' => [
        ['auth_features' => []],
        "The 'optional_modules' key is required in --answers in non-interactive mode.",
    ],
    'non-array optional_modules' => [
        ['auth_features' => [], 'optional_modules' => 'not-an-array'],
        "The 'optional_modules' option must be an array.",
    ],
]);

test('resolve throws RuntimeException when admin creation is requested non-interactively', function (array $commandOptions, array $answers) use ($makeCommand, $makeScript): void {
    $command = $makeCommand(array_merge($commandOptions, [
        '--answers' => json_encode($answers),
        '--no-interaction' => true,
    ]));

    expect(fn (): InstallerContext => InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false))
        ->toThrow(RuntimeException::class, 'Administrator account creation requires secure password provisioning, which cannot be supplied non-interactively.');
})->with([
    '--admin-email flag' => [
        ['--admin-email' => 'admin@example.com'],
        ['auth_features' => [], 'optional_modules' => ['authorization']],
    ],
    '--admin-name flag' => [
        ['--admin-name' => 'Admin'],
        ['auth_features' => [], 'optional_modules' => ['authorization']],
    ],
    'admin key in answers' => [
        [],
        ['auth_features' => [], 'optional_modules' => ['authorization'], 'admin' => ['email' => 'admin@example.com']],
    ],
]);

test('resolve normalizes ai_guidelines from answers', function (mixed $input, bool $expected) use ($makeCommand, $makeScript): void {
    $answers = [
        'auth_features' => [],
        'optional_modules' => [],
        'ai_guidelines' => $input,
    ];

    $command = $makeCommand(['--answers' => json_encode($answers), '--no-interaction' => true]);
    $context = InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false);

    expect($context->includeAiGuidelines)->toBe($expected);
})->with([
    'bool true' => [true, true],
    'bool false' => [false, false],
    'array with guidelines' => [['guidelines'], true],
    'empty array' => [[], false],
]);

test('resolve loads answers from existing InstallerState checkpoint when --answers is omitted', function () use ($makeCommand, $makeScript): void {
    $stateDir = base_path();
    $stateFile = $stateDir.'/'.InstallerState::STATE_FILE;

    $contextOriginal = new InstallerContext(
        providedAnswers: ['auth_features' => ['login'], 'optional_modules' => ['authorization']],
        answers: ['auth_features' => ['login'], 'optional_modules' => ['authorization']],
        paths: [],
        selectedModules: ['authorization'],
        selectedAuthFeatures: ['login'],
        hasAuthorization: true,
        adminName: 'State Admin',
        adminEmail: 'state@example.com',
        isNonInteractive: true,
        skipNode: true,
        isMockedScript: true,
    );

    InstallerState::initialize($stateDir, $contextOriginal);

    try {
        $command = $makeCommand(['--no-interaction' => true]);
        $resolved = InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: false);

        expect($resolved->selectedAuthFeatures)->toBe(['login'])
            ->and($resolved->selectedModules)->toBe(['authorization'])
            ->and($resolved->adminName)->toBe('State Admin')
            ->and($resolved->adminEmail)->toBe('state@example.com');
    } finally {
        File::delete($stateFile);
    }
});

test('resolve interactive mode collects questions from script without --answers', function () use ($makeCommand, $makeScript): void {
    Prompt::fake([Key::ENTER, Key::ENTER]);

    $command = $makeCommand(['--admin-name' => 'Interactive Admin', '--admin-email' => 'interactive@example.com']);
    $context = InstallerContextResolver::resolve($command, $makeScript(), isMockedScript: true, isInteractive: true);

    expect($context->isNonInteractive)->toBeFalse()
        ->and($context->adminName)->toBe('Interactive Admin')
        ->and($context->adminEmail)->toBe('interactive@example.com')
        ->and($context->selectedAuthFeatures)->toBe(['registration']);
});
