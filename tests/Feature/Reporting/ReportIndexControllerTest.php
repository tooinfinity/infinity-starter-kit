<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;

/* @chisel-reporting */

test('unauthenticated users are redirected to login', function (): void {
    $this->get(route('reports.index'))
        ->assertRedirect(route('login'));
});

test('unauthorized users without reports.view cannot access report index', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertForbidden();
});

test('authorized users can view report index with available reports', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('reports/index')
            ->has('reports', 2)
            ->where('reports.0.identifier', 'user_activity')
            ->where('reports.1.identifier', 'audit_activity')
        );
});

/* @end-chisel-reporting */
