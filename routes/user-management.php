<?php

declare(strict_types=1);

use App\Http\Controllers\Users\ActivateUserController;
use App\Http\Controllers\Users\DeactivateUserController;
use App\Http\Controllers\Users\UserController as UserManagementController;
use App\Http\Controllers\Users\UserPasswordController as AdminUserPasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/activate', ActivateUserController::class)->name('users.activate');
    Route::patch('users/{user}/deactivate', DeactivateUserController::class)->name('users.deactivate');
    Route::put('users/{user}/password', AdminUserPasswordController::class)
        ->middleware('password.confirm')
        ->name('users.password.update');
    Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
});
