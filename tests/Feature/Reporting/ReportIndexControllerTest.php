<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Requests\Reporting\AuditReportRequest;
use App\Http\Requests\Reporting\ExportReportRequest;
use App\Http\Requests\Reporting\ReportIndexRequest;
use App\Http\Requests\Reporting\UserReportRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;

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

test('reporting form requests deny authorization when unauthenticated', function (string $requestClass): void {
    /** @var FormRequest $request */
    $request = new $requestClass;
    $request->setUserResolver(fn (): null => null);

    expect($request->authorize())->toBeFalse();
})->with([
    'report index' => [ReportIndexRequest::class],
    'audit report' => [AuditReportRequest::class],
    'user report' => [UserReportRequest::class],
    'export report' => [ExportReportRequest::class],
]);

test('export report request requires both reports.view and reports.export permissions', function (bool $hasView, bool $hasExport, bool $expected): void {
    $user = User::factory()->create();

    if ($hasView) {
        $user->givePermissionTo(PermissionModel::findOrCreate(Permission::ReportsView->value));
    }

    if ($hasExport) {
        $user->givePermissionTo(PermissionModel::findOrCreate(Permission::ReportsExport->value));
    }

    $request = new ExportReportRequest;
    $request->setUserResolver(fn (): User => $user);

    expect($request->authorize())->toBe($expected);
})->with([
    'neither permission' => [false, false, false],
    'only view permission' => [true, false, false],
    'only export permission' => [false, true, false],
    'both permissions' => [true, true, true],
]);
