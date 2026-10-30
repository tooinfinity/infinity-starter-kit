<?php

declare(strict_types=1);

use App\Actions\Users\CreateUser;
use App\Data\Users\CreateUserData;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as RoleModel;

test('CreateUser creates a user with roles', function (): void {
    RoleModel::findOrCreate('editor');

    $action = resolve(CreateUser::class);
    $user = $action->handle(new CreateUserData(
        name: 'John Action',
        email: 'john.action@example.com',
        password: 'password123',
        isActive: true,
        roles: ['editor'],
    ));

    expect($user->exists)->toBeTrue()
        ->and($user->name)->toBe('John Action')
        ->and($user->email)->toBe('john.action@example.com')
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeTrue()
        ->and($user->hasRole('editor'))->toBeTrue();
});
