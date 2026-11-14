<?php

declare(strict_types=1);

namespace App\Actions\Users;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Data\AuditTrails\UserAuditData;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

final readonly class UpdateUserRoles
{
    public function __construct(
        /* @chisel-audit-trails */
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
        /* @end-chisel-audit-trails */
    ) {}

    /**
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $roles/* @chisel-audit-trails */, bool $recordAuditTrail = true/* @end-chisel-audit-trails */): User
    {
        if (! trait_exists(HasRoles::class) || ! $user->exists) {
            return $user;
        }

        return DB::transaction(function () use ($user, $roles/* @chisel-audit-trails */, $recordAuditTrail/* @end-chisel-audit-trails */): User {
            if (enum_exists(Role::class) && ! in_array(Role::SuperAdmin->value, $roles, true)) {
                NotLastSuperAdmin::check(
                    $user,
                    SuperAdminRestrictionOperation::RemoveRole,
                    'roles',
                );
            }

            /* @chisel-audit-trails */
            $oldValues = UserAuditData::fromModel($user)->toArray();
            /* @end-chisel-audit-trails */

            $user->syncRoles($roles);

            /* @chisel-audit-trails */
            if ($recordAuditTrail) {
                $newValues = UserAuditData::fromModel($user, $roles)->toArray();

                $oldRoles = $oldValues['roles'];
                $newRoles = $newValues['roles'];
                sort($oldRoles);
                sort($newRoles);

                if ($oldRoles !== $newRoles) {
                    $this->auditTrail->handle(
                        event: AuditEvent::UserRoleChanged,
                        auditable: $user,
                        oldValues: $oldValues,
                        newValues: $newValues,
                        tags: ['users', 'roles'],
                    );
                }
            }

            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
