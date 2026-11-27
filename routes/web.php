<?php

declare(strict_types=1);

use App\Http\Controllers\DeleteAccountController;
/* @chisel-login */
use App\Http\Controllers\LoginController;
/* @end-chisel-login */
/* @chisel-logout */
use App\Http\Controllers\LogoutController;
/* @end-chisel-logout */
/* @chisel-registration */
use App\Http\Controllers\RegisterController;
/* @end-chisel-registration */
/* @chisel-password-reset */
use App\Http\Controllers\UserEmailResetNotificationController;
/* @end-chisel-password-reset */
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
/* @chisel-two-factor */
use App\Http\Controllers\UserTwoFactorAuthenticationController;
/* @end-chisel-two-factor */
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware(['auth'/* @chisel-email-verification */, 'verified'/* @end-chisel-email-verification */])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
});

Route::middleware('auth')->group(function (): void {
    // User...
    Route::delete('user', DeleteAccountController::class)->name('user.destroy');

    // User Profile...
    Route::get('settings', fn (): RedirectResponse => to_route('user-profile.edit'));
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    /* @chisel-password-update */
    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
    /* @end-chisel-password-update */

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    /* @chisel-two-factor */
    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
    /* @end-chisel-two-factor */
});

Route::middleware('guest')->group(function (): void {
    /* @chisel-registration */
    // Registration...
    Route::get('register', [RegisterController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:register')
        ->name('register.store');
    /* @end-chisel-registration */

    /* @chisel-password-reset */
    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->middleware('throttle:reset-password')
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->middleware('throttle:forgot-password')
        ->name('password.email');
    /* @end-chisel-password-reset */

    /* @chisel-login */
    // Login...
    Route::get('login', [LoginController::class, 'create'])
        ->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->name('login.store');
    /* @end-chisel-login */
});

Route::middleware('auth')->group(function (): void {
    /* @chisel-logout */
    // Logout...
    Route::post('logout', LogoutController::class)
        ->name('logout');
    /* @end-chisel-logout */
});
