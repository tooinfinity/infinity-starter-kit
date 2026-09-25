<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\ReportCategory;
use App\Enums\ReportType;

test('report type enum has valid values', function (): void {
    expect(ReportType::values())->toBe([
        'user_activity',
        'audit_activity',
    ]);
});

test('user activity report type metadata is correct', function (): void {
    $type = ReportType::UserActivity;

    expect($type->value)->toBe('user_activity')
        ->and($type->label())->toBe(__('reports.types.user_activity.title'))
        ->and($type->description())->toBe(__('reports.types.user_activity.description'))
        ->and($type->category())->toBe(ReportCategory::Users)
        ->and($type->route())->toBe(route('reports.users'))
        ->and($type->permission())->toBe(Permission::ReportsView->value);
});

test('audit activity report type metadata is correct', function (): void {
    $type = ReportType::AuditActivity;

    expect($type->value)->toBe('audit_activity')
        ->and($type->label())->toBe(__('reports.types.audit_activity.title'))
        ->and($type->description())->toBe(__('reports.types.audit_activity.description'))
        ->and($type->category())->toBe(ReportCategory::Security)
        ->and($type->route())->toBe(route('reports.audit'))
        ->and($type->permission())->toBe(Permission::ReportsView->value);
});
