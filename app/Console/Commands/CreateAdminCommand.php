<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Spatie\Permission\Models\Role as RoleModel;
use Symfony\Component\Console\Input\Input;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('authorization:create-admin
        {--name= : Name of the administrator}
        {--email= : Email of the administrator}
        {--force : Allow creating or promoting a Super Admin even if one already exists}
        {--stdin : Read the administrator password from STDIN}')]
#[Description('Create an administrator user with the Super Admin role or promote an existing user')]
final class CreateAdminCommand extends Command
{
    /**
     * @var resource|null
     */
    private static mixed $inputStream = null;

    public static function setInputStream(mixed $stream): void
    {
        self::$inputStream = is_resource($stream) ? $stream : null;
    }

    public function handle(): int
    {
        $this->components->info('Provisioning Super Admin user...');

        $superAdminRole = RoleModel::query()->where('name', Role::SuperAdmin->value)->first();
        $superAdminExists = $superAdminRole instanceof RoleModel && User::query()->role(Role::SuperAdmin->value)->exists();

        if ($superAdminExists && ! (bool) $this->option('force')) {
            $this->components->error('A Super Admin already exists. Use --force to proceed.');

            return self::FAILURE;
        }

        $optionName = $this->option('name');
        $optionEmail = $this->option('email');

        $hasName = is_string($optionName) && mb_trim($optionName) !== '';
        $hasEmail = is_string($optionEmail) && mb_trim($optionEmail) !== '';
        $isInteractive = $this->input->isInteractive();

        if (! $isInteractive && ! $hasEmail) {
            $this->components->error('A valid email address is required in non-interactive mode.');

            return self::FAILURE;
        }

        $email = $hasEmail
            ? mb_trim((string) $optionEmail)
            : text(
                label: 'Email',
                required: true,
                validate: ['email' => ['required', 'email', 'max:255']],
            );

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error('A valid email address is required.');

            return self::FAILURE;
        }

        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser instanceof User) {
            if ($existingUser->hasRole(Role::SuperAdmin->value)) {
                $this->components->info(sprintf('User [%s] already has the Super Admin role.', $email));

                return self::SUCCESS;
            }

            $this->components->warn(sprintf('A user with email [%s] already exists.', $email));

            if ($isInteractive && ! $hasEmail && ! $this->components->confirm('Assign the Super Admin role to this existing user?')) {
                $this->components->info('Operation cancelled.');

                return self::SUCCESS;
            }

            RoleModel::findOrCreate(Role::SuperAdmin->value);
            $existingUser->assignRole(Role::SuperAdmin->value);

            $this->components->twoColumnDetail('Existing user promoted', $email);
            $this->components->info('Super Admin role assigned successfully.');

            return self::SUCCESS;
        }

        $name = $hasName
            ? mb_trim((string) $optionName)
            : ($isInteractive && ! $hasEmail
                ? text(
                    label: 'Name',
                    required: true,
                    validate: ['name' => ['required', 'string', 'max:255']],
                )
                : 'Administrator');

        if ($isInteractive && ! (bool) $this->option('stdin')) {
            $inputPassword = password(
                label: 'Password',
                required: true,
                validate: ['password' => ['required', 'string', Password::defaults()]],
            );

            password(
                label: 'Confirm password',
                required: true,
                validate: fn (string $value): ?string => $value === $inputPassword ? null : 'The password confirmation does not match.',
            );
        } else {
            $inputPassword = $this->readPasswordFromStdin();

            if (! is_string($inputPassword) || $inputPassword === '') {
                $this->components->error('A valid password is required via STDIN.');

                return self::FAILURE;
            }

            $validator = Validator::make(
                ['password' => $inputPassword],
                ['password' => ['required', 'string', Password::defaults()]],
            );

            if ($validator->fails()) {
                $this->components->error($validator->errors()->first('password'));

                return self::FAILURE;
            }
        }

        RoleModel::findOrCreate(Role::SuperAdmin->value);

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $inputPassword,
        ]);

        if (Features::enabled(Features::emailVerification())) {
            $user->markEmailAsVerified();
        }

        $user->assignRole(Role::SuperAdmin->value);

        $this->components->twoColumnDetail('Admin user created', $email);
        $this->components->info('Super Admin created successfully.');

        return self::SUCCESS;
    }

    private function readPasswordFromStdin(): ?string
    {
        $stream = self::$inputStream ?? ($this->input instanceof Input ? $this->input->getStream() : null) ?? STDIN;

        if (! is_resource($stream)) {
            return null;
        }

        $line = fgets($stream);

        if ($line === false) {
            return null;
        }

        return mb_rtrim($line, "\r\n");
    }
}
