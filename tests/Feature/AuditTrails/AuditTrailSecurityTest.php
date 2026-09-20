<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\AuditTrail;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;

/* @chisel-audit-trails */

test('guest cannot access audit trails and is redirected to login', function (): void {
    $this->get(route('audit-trails.index'))
        ->assertRedirect(route('login'));
});

test('user without audit.view permission cannot access audit trails', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('audit-trails.index'))
        ->assertForbidden();
});

test('user with audit.view permission can access audit trails', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $this->actingAs($user)
        ->get(route('audit-trails.index'))
        ->assertOk();
});

test('no create update or delete endpoints exist for audit trails', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $record = AuditTrail::factory()->create();

    $this->actingAs($admin)
        ->post('/audit-trails', ['event' => 'fake'])
        ->assertMethodNotAllowed();

    $this->actingAs($admin)
        ->put('/audit-trails/'.$record->id, ['event' => 'fake'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->patch('/audit-trails/'.$record->id, ['event' => 'fake'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->delete('/audit-trails/'.$record->id)
        ->assertNotFound();
});

/* @end-chisel-audit-trails */
