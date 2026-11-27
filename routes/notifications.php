<?php

declare(strict_types=1);

use App\Http\Controllers\MarkAllNotificationsAsReadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    // Notifications...
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'update'])->name('notifications.mark-read');
    Route::patch('notifications/read-all', MarkAllNotificationsAsReadController::class)->name('notifications.mark-all-read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Notification Preferences...
    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
});
