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
use Spatie\Permission\Traits\HasRoles;

final readonly class UpdateUserAction
{
    /* @chisel-audit-trails */
    public function __construct(
        private RecordAuditTrail $auditTrail = new RecordAuditTrail,
    ) {}

    /* @end-chisel-audit-trails */

    public function handle(User $user, UpdateUserData $data, ?User $currentUser = null): User
    {
        if ($data->isActive !== null && $currentUser instanceof User && $user->id === $currentUser->id && ! $data->isActive) {
            throw ValidationException::withMessages([
                'is_active' => __('You cannot deactivate your own account.'),
            ]);
        }

        return DB::transaction(function () use ($user, $data): User {
            $isSuperAdmin = trait_exists(HasRoles::class) && $user->hasRole(Role::SuperAdmin->value);

            if ($isSuperAdmin) {
                $superAdminCount = User::query()
                    ->role(Role::SuperAdmin->value)
                    ->lockForUpdate()
                    ->count();

                if ($superAdminCount <= 1) {
                    if ($data->isActive === false) {
                        throw ValidationException::withMessages([
                            'is_active' => __('Cannot deactivate the last Super Admin.'),
                        ]);
                    }

                    if ($data->roles !== null && ! in_array(Role::SuperAdmin->value, $data->roles, true)) {
                        throw ValidationException::withMessages([
                            'roles' => __('Cannot remove the Super Admin role from the last Super Admin.'),
                        ]);
                    }
                }
            }

            /* @chisel-audit-trails */
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => trait_exists(HasRoles::class) ? $user->getRoleNames()->toArray() : [],
            ];
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

            if ($data->isActive !== null) {
                $updatePayload['is_active'] = $data->isActive;
            }

            $user->update($updatePayload);

            /* @chisel-email-verification */
            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            /* @end-chisel-email-verification */

            if ($data->roles !== null && trait_exists(HasRoles::class)) {
                $user->syncRoles($data->roles);
            }

            /* @chisel-audit-trails */
            $this->auditTrail->handle(
                event: AuditEvent::UserUpdated,
                auditable: $user,
                oldValues: $oldValues,
                newValues: [
                    'name' => $data->name,
                    'email' => $data->email,
                    'is_active' => $user->is_active,
                    'roles' => trait_exists(HasRoles::class) ? $user->getRoleNames()->toArray() : [],
                ],
                tags: ['users'],
            );
            /* @end-chisel-audit-trails */

            return $user;
        });
    }
}
