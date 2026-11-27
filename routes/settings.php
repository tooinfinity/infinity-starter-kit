<?php

declare(strict_types=1);

use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('settings/application', [SettingController::class, 'edit'])
        ->name('settings.edit');
    Route::put('settings/application', [SettingController::class, 'update'])
        ->name('settings.update');
});
