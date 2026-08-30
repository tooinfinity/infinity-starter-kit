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

#[Signature('admin:setup')]
#[Description('Create an administrator user and assign the Super Admin role')]
final class SetupAdminUserCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Setting up administrator user...');

        $name = text(
            label: 'Name',
            required: true,
            validate: ['name' => ['required', 'string', 'max:255']],
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: ['email' => ['required', 'email', 'max:255']],
        );

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser instanceof User) {
            $this->components->warn(sprintf('A user with email [%s] already exists.', $email));

            if (! $this->components->confirm('Assign the Super Admin role to this existing user?')) {
                $this->components->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $existingUser->assignRole(Role::SuperAdmin->value);

            $this->components->twoColumnDetail('Existing user', 'assigned Super Admin role');
            $this->components->info('Admin setup complete.');

            return self::SUCCESS;
        }

        $inputPassword = password(
            label: 'Password',
            required: true,
            validate: ['password' => ['required', 'string', 'min:8']],
        );

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
