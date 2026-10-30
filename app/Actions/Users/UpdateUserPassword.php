<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Models\User;
/* @chisel-notifications */
use App\Notifications\PasswordChanged;
/* @end-chisel-notifications */
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class UpdateUserPassword
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        /* @end-chisel-audit-trails */
    ) {}

    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            if (config('session.driver') === 'database') {
                $connection = config('session.connection');
                $connectionName = is_string($connection) ? $connection : null;

                DB::connection($connectionName)
                    ->table(config()->string('session.table', 'sessions'))
                    ->where('user_id', $user->getAuthIdentifier())
                    ->delete();
            }

            /* @chisel-notifications */
            $user->notify(new PasswordChanged);
            /* @end-chisel-notifications */

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserPasswordChanged,
                auditable: $user,
                newValues: ['password' => '[REDACTED]'],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */
        });
    }
}
