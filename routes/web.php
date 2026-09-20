<?php

declare(strict_types=1);

/* @chisel-audit-trails */
use App\Http\Controllers\AuditTrails\AuditTrailController;
/* @end-chisel-audit-trails */
/* @chisel-localization */
use App\Http\Controllers\LocaleController;
/* @end-chisel-localization */
/* @chisel-notifications */
use App\Http\Controllers\MarkAllNotificationsAsReadController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
/* @end-chisel-notifications */
use App\Http\Controllers\SessionController;
/* @chisel-settings */
use App\Http\Controllers\SettingController;
/* @end-chisel-settings */
/* @chisel-user-management */
use App\Http\Controllers\UserController;
/* @end-chisel-user-management */
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\Users\ActivateUserController;
use App\Http\Controllers\Users\DeactivateUserController;
use App\Http\Controllers\Users\UserController as UserManagementController;
use App\Http\Controllers\Users\UserPasswordController as AdminUserPasswordController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

/* @chisel-localization */
Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');
/* @end-chisel-localization */

Route::middleware(['auth'/* @chisel-email-verification */, 'verified'/* @end-chisel-email-verification */])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
});

Route::middleware('auth')->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    /* @chisel-two-factor-authentication */
    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
    /* @end-chisel-two-factor-authentication */

    /* @chisel-settings */
    // Application Settings...
    Route::get('settings/application', [SettingController::class, 'edit'])
        ->middleware('can:settings.manage')
        ->name('settings.edit');
    Route::put('settings/application', [SettingController::class, 'update'])
        ->name('settings.update');
    /* @end-chisel-settings */

    /* @chisel-user-management */
    // User Management...
    Route::middleware('can:users.view')->group(function (): void {
        Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserManagementController::class, 'create'])
            ->middleware('can:users.create')
            ->name('users.create');
        Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])
            ->middleware('can:users.update')
            ->name('users.edit');
        Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::patch('users/{user}/activate', ActivateUserController::class)->name('users.activate');
        Route::patch('users/{user}/deactivate', DeactivateUserController::class)->name('users.deactivate');
        Route::put('users/{user}/password', AdminUserPasswordController::class)->name('users.password.update');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });
    /* @end-chisel-user-management */

    /* @chisel-notifications */
    // Notifications...
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'update'])->name('notifications.mark-read');
    Route::patch('notifications/read-all', MarkAllNotificationsAsReadController::class)->name('notifications.mark-all-read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Notification Preferences...
    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
    /* @end-chisel-notifications */

    /* @chisel-audit-trails */
    // Audit Trails...
    Route::get('audit-trails', [AuditTrailController::class, 'index'])
        ->middleware('can:audit.view')
        ->name('audit-trails.index');
    /* @end-chisel-audit-trails */
});

Route::middleware('guest')->group(function (): void {
    /* @chisel-registration */
    // User...
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->name('register.store');
    /* @end-chisel-registration */

    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    /* @chisel-email-verification */
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('user.verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('user.verification.verify');
    /* @end-chisel-email-verification */

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});
