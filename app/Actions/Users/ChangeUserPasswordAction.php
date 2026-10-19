<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

final readonly class ChangeUserPasswordAction
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        /* @end-chisel-audit-trails */
        private EnsureActorMayManageTargetAction $ensureActorMayManageTarget = new EnsureActorMayManageTargetAction,
    ) {}

    public function handle(User $user, #[SensitiveParameter] string $password, ?User $currentUser = null): void
    {
        if ($currentUser instanceof User) {
            $this->ensureActorMayManageTarget->ensure($currentUser, $user);
        }

        DB::transaction(function () use ($user, $password): void {
            $user->forceFill([
                'password' => $password,
            ])->save();

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
