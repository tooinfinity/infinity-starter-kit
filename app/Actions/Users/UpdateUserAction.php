<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
/* @end-chisel-audit-trails */
use App\Data\Users\UpdateUserData;
/* @chisel-audit-trails */
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\DB;

final readonly class UpdateUserAction
{
    public function __construct(
        private UpdateUserRolesAction $updateUserRoles = new UpdateUserRolesAction,
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        private SnapshotUserAuditDataAction $snapshotUserAuditData = new SnapshotUserAuditDataAction,
        /* @end-chisel-audit-trails */
    ) {}

    public function handle(User $user, UpdateUserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            if ($data->roles !== null && enum_exists(Role::class) && ! in_array(Role::SuperAdmin->value, $data->roles, true)) {
                NotLastSuperAdmin::check($user, SuperAdminRestrictionOperation::RemoveRole, 'roles');
            }

            /* @chisel-audit-trails */
            $oldValues = $this->snapshotUserAuditData->handle($user);
            /* @end-chisel-audit-trails */

            /* @chisel-email-verification */
            $emailChanged = $user->email !== $data->email;
            /* @end-chisel-email-verification */

            $updatePayload = [
                'name' => $data->name,
                'email' => $data->email,
                /* @chisel-email-verification */
                ...($emailChanged ? ['email_verified_at' => null] : []),
                /* @end-chisel-email-verification */
            ];

            $user->update($updatePayload);

            /* @chisel-email-verification */
            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            /* @end-chisel-email-verification */

            if ($data->roles !== null) {
                $this->updateUserRoles->handle($user, $data->roles, recordAuditTrail: false);
            }

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserUpdated,
                auditable: $user,
                oldValues: $oldValues,
                newValues: $this->snapshotUserAuditData->handle($user, $data->roles),
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
