<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('admin:setup
        {--name= : Name of the administrator}
        {--email= : Email of the administrator}
        {--password= : Password for the administrator}')]
#[Description('Create an administrator user and assign the Super Admin role')]
final class SetupAdminUserCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Setting up administrator user...');

        $optionName = $this->option('name');
        $optionEmail = $this->option('email');
        $optionPassword = $this->option('password');

        $isInteractive = $this->input->isInteractive();

        if (! $isInteractive && $optionEmail === null) {
            $this->components->info('No administrator credentials provided in non-interactive mode; skipping administrator creation.');

            return self::SUCCESS;
        }

        $name = is_string($optionName) && $optionName !== ''
            ? $optionName
            : ($optionEmail !== null
                ? 'Administrator'
                : text(
                    label: 'Name',
                    required: true,
                    validate: ['name' => ['required', 'string', 'max:255']],
                ));

        $email = is_string($optionEmail) && $optionEmail !== ''
            ? $optionEmail
            : text(
                label: 'Email',
                required: true,
                validate: ['email' => ['required', 'email', 'max:255']],
            );

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser instanceof User) {
            $this->components->warn(sprintf('A user with email [%s] already exists.', $email));

            if ($optionEmail === null && ! $this->components->confirm('Assign the Super Admin role to this existing user?')) {
                $this->components->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $existingUser->assignRole(Role::SuperAdmin->value);

            $this->components->twoColumnDetail('Existing user', 'assigned Super Admin role');
            $this->components->info('Admin setup complete.');

            return self::SUCCESS;
        }

        if (is_string($optionPassword) && $optionPassword !== '') {
            if (mb_strlen($optionPassword) < 8) {
                $this->components->error('The administrator password must be at least 8 characters.');

                return self::FAILURE;
            }

            $inputPassword = $optionPassword;
        } elseif ($isInteractive) {
            $inputPassword = password(
                label: 'Password',
                required: true,
                validate: ['password' => ['required', 'string', 'min:8']],
            );
        } else {
            $this->components->error('A password of at least 8 characters is required to create an administrator user in non-interactive mode.');

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $inputPassword,
        ]);

        $user->assignRole(Role::SuperAdmin->value);

        $this->components->twoColumnDetail('Admin user created', $email);
        $this->components->info('Admin setup complete.');

        return self::SUCCESS;
    }
}
