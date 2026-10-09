<?php

declare(strict_types=1);

use App\Chisel\Installer\GeneratedApplicationConfigurator;
use App\Chisel\Installer\InstallerContext;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function createTestInstallerContext(
    bool $hasAuthorization = true,
    bool $isNonInteractive = false,
    ?string $adminName = null,
    ?string $adminEmail = null,
    ?string $adminPassword = null,
    array $providedAnswers = [],
): InstallerContext {
    return new InstallerContext(
        providedAnswers: $providedAnswers,
        answers: [],
        paths: [],
        selectedModules: $hasAuthorization ? ['authorization'] : [],
        selectedAuthFeatures: ['registration'],
        hasAuthorization: $hasAuthorization,
        adminName: $adminName,
        adminEmail: $adminEmail,
        adminPassword: $adminPassword,
        isNonInteractive: $isNonInteractive,
        skipNode: true,
        isMockedScript: true,
    );
}

test('configure returns success immediately when authorization is disabled', function (): void {
    $command = new class extends Command {};
    $context = createTestInstallerContext(hasAuthorization: false);

    expect(GeneratedApplicationConfigurator::configure($command, $context))->toBe(Command::SUCCESS);
});

test('configure handles authorization:setup exception with and without components', function (): void {
    $context = createTestInstallerContext(hasAuthorization: true);

    // With components
    $failingCommand = new class extends Command
    {
        public function call($command, array $arguments = []): int
        {
            throw new RuntimeException('DB connection failed');
        }
    };
    $output = new BufferedOutput;
    $components = new Factory(new OutputStyle(new ArrayInput([]), $output));

    expect(GeneratedApplicationConfigurator::configure($failingCommand, $context, $components))->toBe(Command::FAILURE);

    // Without components
    $commandWithoutComponents = new class extends Command
    {
        public array $errors = [];

        public function call($command, array $arguments = []): int
        {
            throw new RuntimeException('DB connection failed direct');
        }

        public function error($string, $verbosity = null): void
        {
            $this->errors[] = $string;
        }
    };

    expect(GeneratedApplicationConfigurator::configure($commandWithoutComponents, $context))->toBe(Command::FAILURE)
        ->and($commandWithoutComponents->errors)->not->toBeEmpty();
});

test('configure handles authorization:setup non-zero exit without components', function (): void {
    $context = createTestInstallerContext(hasAuthorization: true);

    $command = new class extends Command
    {
        public array $errors = [];

        public function call($command, array $arguments = []): int
        {
            return 2;
        }

        public function error($string, $verbosity = null): void
        {
            $this->errors[] = $string;
        }
    };

    expect(GeneratedApplicationConfigurator::configure($command, $context))->toBe(2)
        ->and($command->errors)->toContain('Authorization setup failed.');
});

test('configure handles setupAdminUser exception with and without components', function (): void {
    $context = createTestInstallerContext(
        hasAuthorization: true,
        adminEmail: 'admin@example.com',
        adminPassword: 'password123',
    );

    // With components
    $command = new class extends Command
    {
        public function call($command, array $arguments = []): int
        {
            if ($command === 'authorization:setup') {
                return Command::SUCCESS;
            }

            throw new RuntimeException('Admin role assignment failed');
        }
    };
    $output = new BufferedOutput;
    $components = new Factory(new OutputStyle(new ArrayInput([]), $output));

    expect(GeneratedApplicationConfigurator::configure($command, $context, $components))->toBe(Command::FAILURE);

    // Without components
    $commandWithoutComponents = new class extends Command
    {
        public array $errors = [];

        public function call($command, array $arguments = []): int
        {
            if ($command === 'authorization:setup') {
                return Command::SUCCESS;
            }

            throw new RuntimeException('Admin role assignment failed direct');
        }

        public function error($string, $verbosity = null): void
        {
            $this->errors[] = $string;
        }
    };

    expect(GeneratedApplicationConfigurator::configure($commandWithoutComponents, $context))->toBe(Command::FAILURE)
        ->and($commandWithoutComponents->errors)->not->toBeEmpty();
});

test('configure handles admin:setup non-zero exit without components', function (): void {
    $context = createTestInstallerContext(
        hasAuthorization: true,
        adminEmail: 'admin@example.com',
        adminPassword: 'password123',
    );

    $command = new class extends Command
    {
        public array $errors = [];

        public function call($command, array $arguments = []): int
        {
            if ($command === 'authorization:setup') {
                return Command::SUCCESS;
            }

            return 3;
        }

        public function error($string, $verbosity = null): void
        {
            $this->errors[] = $string;
        }
    };

    expect(GeneratedApplicationConfigurator::configure($command, $context))->toBe(3)
        ->and($command->errors)->toContain('Administrator setup failed.');
});

test('setupAdminUser calls admin:setup in interactive mode when no explicit credentials given', function (): void {
    $context = createTestInstallerContext(hasAuthorization: true, isNonInteractive: false);

    $command = new class extends Command
    {
        public array $calls = [];

        public function call($command, array $arguments = []): int
        {
            $this->calls[] = $command;

            return Command::SUCCESS;
        }
    };

    expect(GeneratedApplicationConfigurator::configure($command, $context))->toBe(Command::SUCCESS)
        ->and($command->calls)->toBe(['authorization:setup', 'admin:setup']);
});

test('setupAdminUser skips admin in non-interactive mode with info logged without components', function (): void {
    $context = createTestInstallerContext(hasAuthorization: true, isNonInteractive: true);

    $command = new class extends Command
    {
        public array $infos = [];

        public function call($command, array $arguments = []): int
        {
            return Command::SUCCESS;
        }

        public function info($string, $verbosity = null): void
        {
            $this->infos[] = $string;
        }
    };

    expect(GeneratedApplicationConfigurator::configure($command, $context))->toBe(Command::SUCCESS)
        ->and($command->infos)->toContain('No administrator credentials provided in non-interactive mode; skipping administrator creation.');
});

test('configure handles authorization:setup non-zero exit with components', function (): void {
    $context = createTestInstallerContext(hasAuthorization: true);

    $command = new class extends Command
    {
        public function call($command, array $arguments = []): int
        {
            return 4;
        }
    };
    $output = new BufferedOutput;
    $components = new Factory(new OutputStyle(new ArrayInput([]), $output));

    expect(GeneratedApplicationConfigurator::configure($command, $context, $components))->toBe(4)
        ->and($output->fetch())->toContain('Authorization setup failed.');
});

test('configure handles admin:setup non-zero exit with components', function (): void {
    $context = createTestInstallerContext(
        hasAuthorization: true,
        adminEmail: 'admin@example.com',
        adminPassword: 'password123',
    );

    $command = new class extends Command
    {
        public function call($command, array $arguments = []): int
        {
            if ($command === 'authorization:setup') {
                return Command::SUCCESS;
            }

            return 5;
        }
    };
    $output = new BufferedOutput;
    $components = new Factory(new OutputStyle(new ArrayInput([]), $output));

    expect(GeneratedApplicationConfigurator::configure($command, $context, $components))->toBe(5)
        ->and($output->fetch())->toContain('Administrator setup failed.');
});

test('setupAdminUser passes adminName and no-interaction flags when credentials present non-interactively', function (): void {
    $context = createTestInstallerContext(
        hasAuthorization: true,
        isNonInteractive: true,
        adminName: 'Root Admin',
        adminEmail: 'admin@example.com',
        adminPassword: 'secretpassword',
    );

    $command = new class extends Command
    {
        public array $capturedArgs = [];

        public function call($command, array $arguments = []): int
        {
            if ($command === 'admin:setup') {
                $this->capturedArgs = $arguments;
            }

            return Command::SUCCESS;
        }
    };

    expect(GeneratedApplicationConfigurator::configure($command, $context))->toBe(Command::SUCCESS)
        ->and($command->capturedArgs)->toBe([
            '--name' => 'Root Admin',
            '--email' => 'admin@example.com',
            '--password' => 'secretpassword',
            '--no-interaction' => true,
        ]);
});

test('setupAdminUser skips admin in non-interactive mode with info logged to components', function (): void {
    $context = createTestInstallerContext(hasAuthorization: true, isNonInteractive: true);

    $command = new class extends Command
    {
        public function call($command, array $arguments = []): int
        {
            return Command::SUCCESS;
        }
    };
    $output = new BufferedOutput;
    $components = new Factory(new OutputStyle(new ArrayInput([]), $output));

    expect(GeneratedApplicationConfigurator::configure($command, $context, $components))->toBe(Command::SUCCESS)
        ->and($output->fetch())->toContain('No administrator credentials provided in non-interactive mode; skipping administrator creation.');
});
