<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StartController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? '/home' : '/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
    Route::post('/signup', [AuthController::class, 'signup'])->middleware('throttle:signup');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->name('password.update');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/home', StartController::class)->name('start');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('/profile/theme', [ProfileController::class, 'theme'])->name('profile.theme');

    Route::middleware('super')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::post('/organizations', [AdminController::class, 'store'])->name('store');
        Route::post('/organizations/{organization}/{action}', [AdminController::class, 'transition'])->name('transition');
        Route::post('/reset-link', [AdminController::class, 'resetLink'])->name('reset-link');
        Route::get('/maintenance', [AdminController::class, 'maintenance'])->name('maintenance');
        Route::post('/maintenance/migrate', [AdminController::class, 'migrate'])->name('migrate');
        Route::post('/maintenance/clear', [AdminController::class, 'clearCaches'])->name('clear');
    });

    Route::prefix('o/{organization:slug}')->middleware(['member', 'scheduler'])->name('org.')->group(function () {
        require __DIR__.'/tenant.php';
    });
});
