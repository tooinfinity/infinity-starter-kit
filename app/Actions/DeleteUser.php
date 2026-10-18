<?php

declare(strict_types=1);

namespace App\Actions;

/* @chisel-audit-trails */
use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
/* @end-chisel-audit-trails */
/* @chisel-roles-permissions */
use App\Enums\Role;
/* @end-chisel-roles-permissions */
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
/* @chisel-roles-permissions */
use Spatie\Permission\Traits\HasRoles;

/* @end-chisel-roles-permissions */

final readonly class DeleteUser
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            /* @chisel-roles-permissions */
            if (trait_exists(HasRoles::class) && enum_exists(Role::class) && $user->hasRole(Role::SuperAdmin->value)) {
                $superAdmins = User::query()
                    ->role(Role::SuperAdmin->value)
                    ->lockForUpdate()
                    ->get(['users.id']);

                if (count($superAdmins) <= 1) {
                    throw ValidationException::withMessages([
                        'user' => __('Cannot delete the last Super Admin.'),
                    ]);
                }
            }

            /* @end-chisel-roles-permissions */

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserDeleted,
                auditable: $user,
                oldValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    /* @chisel-roles-permissions */
                    'roles' => trait_exists(HasRoles::class) ? $user->getRoleNames()->toArray() : [],
                    /* @end-chisel-roles-permissions */
                ],
                user: $user,
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            /* @chisel-notifications */
            $user->notifications()->delete();
            /* @end-chisel-notifications */

            $user->delete();
        });
    }
}
