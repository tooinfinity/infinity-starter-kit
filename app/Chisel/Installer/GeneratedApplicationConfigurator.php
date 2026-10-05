<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Console\Command;
use Illuminate\Console\View\Components\Factory;
use Throwable;

/**
 * Handles post-scaffolding application configuration, including authorization setup and admin account creation.
 */
final class GeneratedApplicationConfigurator
{
    public static function configure(
        Command $command,
        InstallerContext $context,
        ?Factory $components = null,
    ): int {
        if (! $context->hasAuthorization) {
            return Command::SUCCESS;
        }

        try {
            $authStatus = $command->call('authorization:setup');
        } catch (Throwable $throwable) {
            $message = 'Authorization setup failed: '.$context->redact($throwable->getMessage());
            if ($components instanceof Factory) {
                $components->error($message);
            } else {
                $command->error($message);
            }

            return Command::FAILURE;
        }

        if ($authStatus !== Command::SUCCESS) {
            if ($components instanceof Factory) {
                $components->error('Authorization setup failed.');
            } else {
                $command->error('Authorization setup failed.');
            }

            return $authStatus;
        }

        try {
            $adminStatus = self::setupAdminUser($command, $context, $components);
        } catch (Throwable $throwable) {
            $message = 'Administrator setup failed: '.$context->redact($throwable->getMessage());
            if ($components instanceof Factory) {
                $components->error($message);
            } else {
                $command->error($message);
            }

            return Command::FAILURE;
        }

        if ($adminStatus !== Command::SUCCESS) {
            if ($components instanceof Factory) {
                $components->error('Administrator setup failed.');
            } else {
                $command->error('Administrator setup failed.');
            }

            return $adminStatus;
        }

        return Command::SUCCESS;
    }

    private static function setupAdminUser(
        Command $command,
        InstallerContext $context,
        ?Factory $components = null,
    ): int {
        $adminParams = [];
        if ($context->adminName !== null) {
            $adminParams['--name'] = $context->adminName;
        }

        if ($context->adminEmail !== null) {
            $adminParams['--email'] = $context->adminEmail;
        }

        if ($context->adminPassword !== null) {
            $adminParams['--password'] = $context->adminPassword;
        }

        $hasExplicitCredentials = $context->adminEmail !== null
            || $context->adminName !== null
            || $context->adminPassword !== null
            || (is_array($context->providedAnswers['admin'] ?? null) && $context->providedAnswers['admin'] !== []);

        if ($hasExplicitCredentials) {
            if ($context->isNonInteractive) {
                $adminParams['--no-interaction'] = true;
            }

            return $command->call('admin:setup', $adminParams);
        }

        if (! $context->isNonInteractive) {
            return $command->call('admin:setup', $adminParams);
        }

        $info = 'No administrator credentials provided in non-interactive mode; skipping administrator creation.';
        if ($components instanceof Factory) {
            $components->info($info);
        } else {
            $command->info($info);
        }

        return Command::SUCCESS;
    }
}
