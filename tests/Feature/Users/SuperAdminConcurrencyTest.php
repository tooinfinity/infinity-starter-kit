<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Actions\Users\DeactivateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Actions\Users\UpdateUserRolesAction;
use App\Data\Users\UpdateUserData;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;
use Symfony\Component\Process\Process;

it('serializes simultaneous super admin restrictions with role removal', function (string $actionClass): void {
    $originalConnection = DB::getDefaultConnection();
    $originalPdo = DB::connection()->getPdo();
    $schema = 'super_admin_'.Str::lower(Str::random(16));
    $process = null;

    // A separate schema/connection gives the worker committed fixtures outside LazilyRefreshDatabase.
    $originalPdo->exec('CREATE SCHEMA "'.$schema.'"');
    config(['database.connections.concurrency' => [
        ...DB::connection()->getConfig(),
        'name' => 'concurrency',
        'search_path' => $schema,
    ]]);
    DB::setDefaultConnection('concurrency');

    try {
        $this->artisan('migrate', ['--database' => 'concurrency', '--force' => true])->assertSuccessful();

        $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
        $first = User::factory()->active()->create();
        $second = User::factory()->active()->create();
        $first->assignRole($role);
        $second->assignRole($role);

        $worker = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            config([
                'database.connections.concurrency' => json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR),
                'database.default' => 'concurrency',
                'cache.default' => 'array',
            ]);
            Illuminate\Support\Facades\DB::select("select set_config('application_name', ?, false)", [$argv[2]]);
            Illuminate\Support\Facades\DB::statement("SET lock_timeout = '10s'");
            $user = App\Models\User::findOrFail($argv[1]);
            $user->load('roles');
            echo "ready\n";
            flush();

            try {
                resolve(App\Actions\Users\UpdateUserRolesAction::class)->handle($user, []);
                echo json_encode(['removed' => true], JSON_THROW_ON_ERROR)."\n";
            } catch (Illuminate\Validation\ValidationException $exception) {
                echo json_encode($exception->errors(), JSON_THROW_ON_ERROR)."\n";
            }
            PHP;

        DB::beginTransaction();
        $backend = DB::selectOne('select pg_backend_pid() as pid');
        match ($actionClass) {
            UpdateUserRolesAction::class => resolve(UpdateUserRolesAction::class)->handle($first, []),
            UpdateUserAction::class => resolve(UpdateUserAction::class)->handle($first, new UpdateUserData($first->name, $first->email, [])),
            default => resolve($actionClass)->handle($first),
        };

        $process = new Process([PHP_BINARY, '-r', $worker, $second->id, $schema], base_path(), timeout: 15);
        $process->setInput(json_encode(DB::connection()->getConfig(), JSON_THROW_ON_ERROR));
        $process->start();
        expect($process->waitUntil(fn (string $type, string $output): bool => str_contains($output, "ready\n")))->toBeTrue();

        $deadline = microtime(true) + 5;
        Sleep::fake(false);
        do {
            $waiting = DB::selectOne(
                'select exists (select 1 from pg_stat_activity where application_name = ? and ? = any(pg_blocking_pids(pid))) as blocked',
                [$schema, $backend->pid],
            );

            if ($waiting->blocked) {
                break;
            }

            Sleep::usleep(10_000);
        } while (microtime(true) < $deadline && $process->isRunning());

        expect($waiting->blocked)->toBeTrue('The second transaction must wait for the first restriction to commit.');
        DB::commit();

        expect($process->wait())->toBe(0, $process->getErrorOutput())
            ->and(json_decode(mb_trim(str_replace("ready\n", '', $process->getOutput())), true, flags: JSON_THROW_ON_ERROR))
            ->toBe(['roles' => [__('Cannot remove the Super Admin role from the last Super Admin.')]])
            ->and(User::query()->role(Role::SuperAdmin->value)->where('is_active', true)->count())->toBe(1)
            ->and($second->refresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
    } finally {
        Sleep::fake();
        $process?->stop(0);

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::purge('concurrency');
        DB::setDefaultConnection($originalConnection);
        $originalPdo->exec('DROP SCHEMA "'.$schema.'" CASCADE');
    }
})->with([
    'remove role' => UpdateUserRolesAction::class,
    'update user roles' => UpdateUserAction::class,
    'deactivate' => DeactivateUserAction::class,
    'delete user' => DeleteUserAction::class,
    'delete account' => DeleteUser::class,
])->skip(fn (): bool => DB::connection()->getDriverName() !== 'pgsql', 'Requires PostgreSQL row locking.');
