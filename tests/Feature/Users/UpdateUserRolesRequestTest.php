<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Requests\Users\UpdateUserRolesRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('authorizes user with UsersManageRoles permission', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersManageRoles->value);
    $user->givePermissionTo($permission);

    $request = new UpdateUserRolesRequest;
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeTrue();
});

it('denies authorization for user without UsersManageRoles permission or guest', function (): void {
    $user = User::factory()->create();

    $requestWithUser = new UpdateUserRolesRequest;
    $requestWithUser->setUserResolver(fn () => $user);

    expect($requestWithUser->authorize())->toBeFalse();

    $requestAsGuest = new UpdateUserRolesRequest;
    $requestAsGuest->setUserResolver(fn (): null => null);

    expect($requestAsGuest->authorize())->toBeFalse();
});

it('validates roles array and ensures role names exist in database', function (): void {
    RoleModel::findOrCreate('editor');
    $request = new UpdateUserRolesRequest;

    $validValidator = Validator::make([
        'roles' => ['editor'],
    ], $request->rules());

    expect($validValidator->passes())->toBeTrue();

    $missingValidator = Validator::make([], $request->rules());
    expect($missingValidator->fails())->toBeTrue()
        ->and($missingValidator->errors()->has('roles'))->toBeTrue();

    $notArrayValidator = Validator::make([
        'roles' => 'editor',
    ], $request->rules());
    expect($notArrayValidator->fails())->toBeTrue()
        ->and($notArrayValidator->errors()->has('roles'))->toBeTrue();

    $nonExistentValidator = Validator::make([
        'roles' => ['does-not-exist'],
    ], $request->rules());
    expect($nonExistentValidator->fails())->toBeTrue()
        ->and($nonExistentValidator->errors()->has('roles.0'))->toBeTrue();
});
