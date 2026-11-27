<?php

declare(strict_types=1);

use App\Http\Controllers\AuditTrails\AuditTrailController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('audit-trails', [AuditTrailController::class, 'index'])
        ->name('audit-trails.index');
});
