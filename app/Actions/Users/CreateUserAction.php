<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
/* @end-chisel-audit-trails */
use App\Data\Users\CreateUserData;
/* @chisel-audit-trails */
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

final readonly class CreateUserAction
{
    public function __construct(
        private UpdateUserRolesAction $updateUserRoles = new UpdateUserRolesAction,
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        private SnapshotUserAuditDataAction $snapshotUserAuditData = new SnapshotUserAuditDataAction,
        /* @end-chisel-audit-trails */
    ) {}

    public function handle(CreateUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
                'is_active' => $data->isActive,
            ]);

            if ($data->roles !== []) {
                $this->updateUserRoles->handle($user, $data->roles, recordAuditTrail: false);
            }

            event(new Registered($user));

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserCreated,
                auditable: $user,
                newValues: $this->snapshotUserAuditData->handle($user, $data->roles),
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
