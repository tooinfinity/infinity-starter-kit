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
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateUserAction
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    public function handle(User $user, UpdateUserData $data, ?User $currentUser = null): User
    {
        if ($currentUser instanceof User && $user->id === $currentUser->id && ! $data->isActive) {
            throw ValidationException::withMessages([
                'is_active' => __('You cannot deactivate your own account.'),
            ]);
        }

        $isSuperAdmin = $user->hasRole(Role::SuperAdmin->value);

        if ($isSuperAdmin) {
            $superAdminCount = User::query()->role(Role::SuperAdmin->value)->count();

            if ($superAdminCount <= 1) {
                if (! $data->isActive) {
                    throw ValidationException::withMessages([
                        'is_active' => __('Cannot deactivate the last Super Admin.'),
                    ]);
                }

                if (! in_array(Role::SuperAdmin->value, $data->roles, true)) {
                    throw ValidationException::withMessages([
                        'roles' => __('Cannot remove the Super Admin role from the last Super Admin.'),
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($user, $data): User {
            /* @chisel-audit-trails */
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->getRoleNames()->toArray(),
            ];
            /* @end-chisel-audit-trails */

            $emailChanged = $user->email !== $data->email;

            $user->update([
                'name' => $data->name,
                'email' => $data->email,
                'is_active' => $data->isActive,
                /* @chisel-email-verification */
                ...($emailChanged ? ['email_verified_at' => null] : []),
                /* @end-chisel-email-verification */
            ]);

            /* @chisel-email-verification */
            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            /* @end-chisel-email-verification */

            $user->syncRoles($data->roles);

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserUpdated,
                auditable: $user,
                oldValues: $oldValues,
                newValues: [
                    'name' => $data->name,
                    'email' => $data->email,
                    'is_active' => $data->isActive,
                    'roles' => $data->roles,
                ],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
