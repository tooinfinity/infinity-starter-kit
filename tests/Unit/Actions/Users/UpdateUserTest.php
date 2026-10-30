<?php

declare(strict_types=1);

use App\Actions\Users\UpdateUser;
use App\Data\Users\UpdateUserData;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

test('UpdateUser updates user attributes and roles', function (): void {
    RoleModel::findOrCreate('manager');

    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    $action = resolve(UpdateUser::class);
    $action->handle($user, new UpdateUserData(
        name: 'Updated Name',
        email: 'original@example.com',
        roles: ['manager'],
    ));

    expect($user->fresh()->name)->toBe('Updated Name')
        ->and($user->fresh()->hasRole('manager'))->toBeTrue();
});
