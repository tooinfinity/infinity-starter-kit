<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

final readonly class UpdateUserRolesAction
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        private SnapshotUserAuditDataAction $snapshotUserAuditData = new SnapshotUserAuditDataAction,
        /* @end-chisel-audit-trails */
    ) {}

    /**
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $roles, bool $recordAuditTrail = true): User
    {
        if (! trait_exists(HasRoles::class) || ! $user->exists) {
            return $user;
        }

        return DB::transaction(function () use ($user, $roles, $recordAuditTrail): User {
            if (enum_exists(Role::class) && ! in_array(Role::SuperAdmin->value, $roles, true)) {
                NotLastSuperAdmin::check(
                    $user,
                    SuperAdminRestrictionOperation::RemoveRole,
                    'roles',
                );
            }

            /* @chisel-audit-trails */
            $oldValues = $this->snapshotUserAuditData->handle($user);
            /* @end-chisel-audit-trails */

            $user->syncRoles($roles);

            /* @chisel-audit-trails */
            if ($recordAuditTrail) {
                $newValues = $this->snapshotUserAuditData->handle($user, $roles);

                $this->auditTrail->handle(
                    event: AuditEvent::UserUpdated,
                    auditable: $user,
                    oldValues: $oldValues,
                    newValues: $newValues,
                    tags: ['users', 'roles'],
                );
            }

            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
