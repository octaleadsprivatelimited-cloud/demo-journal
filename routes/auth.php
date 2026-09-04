<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthenticationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'chooser'])->name('login');
    Route::get('/author/login', [AuthenticatedSessionController::class, 'createAuthor'])->name('author.login');
    Route::post('/author/login', [AuthenticatedSessionController::class, 'storeAuthor'])->middleware('throttle:login')->name('author.login.store');
    Route::get('/editor/login', [AuthenticatedSessionController::class, 'createEditor'])->name('editor.login');
    Route::post('/editor/login', [AuthenticatedSessionController::class, 'storeEditor'])->middleware('throttle:login')->name('editor.login.store');
    Route::get('/reviewer/login', [AuthenticatedSessionController::class, 'createReviewer'])->name('reviewer.login');
    Route::post('/reviewer/login', [AuthenticatedSessionController::class, 'storeReviewer'])->middleware('throttle:login')->name('reviewer.login.store');
    Route::get('/admin/login', [AuthenticatedSessionController::class, 'createAdmin'])->name('admin.login');
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'storeAdmin'])->middleware('throttle:login')->name('admin.login.store');
    Route::get('/contributor/login', [AuthenticatedSessionController::class, 'createContributor'])->name('contributor.login');
    Route::post('/contributor/login', [AuthenticatedSessionController::class, 'storeContributor'])->middleware('throttle:login')->name('contributor.login.store');
    Route::post('/auth/google/{portal}/one-tap', [GoogleAuthenticationController::class, 'oneTap'])->whereIn('portal', ['author', 'editor', 'reviewer', 'contributor', 'admin'])->middleware('throttle:login')->name('google.one-tap');
    Route::get('/auth/google/{portal}', [GoogleAuthenticationController::class, 'redirect'])
        ->whereIn('portal', ['author', 'editor', 'reviewer', 'contributor', 'admin'])
        ->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthenticationController::class, 'callback'])->name('google.callback');

    Route::get('/register', [RegisteredUserController::class, 'chooser'])->name('register');
    Route::middleware('author-registration')->group(function (): void {
        Route::get('/author/register', [RegisteredUserController::class, 'createAuthor'])->name('author.register');
        Route::post('/author/register', [RegisteredUserController::class, 'storeAuthor'])->middleware('throttle:6,1')->name('author.register.store');
    });
    Route::get('/editor/register', [RegisteredUserController::class, 'createEditor'])->name('editor.register');
    Route::post('/editor/register', [RegisteredUserController::class, 'storeEditor'])->middleware('throttle:6,1')->name('editor.register.store');
    Route::get('/reviewer/register', [RegisteredUserController::class, 'createReviewer'])->name('reviewer.register');
    Route::post('/reviewer/register', [RegisteredUserController::class, 'storeReviewer'])->middleware('throttle:6,1')->name('reviewer.register.store');
    Route::get('/contributor/register', [RegisteredUserController::class, 'createContributor'])->name('contributor.register');
    Route::post('/contributor/register', [RegisteredUserController::class, 'storeContributor'])->middleware('throttle:6,1')->name('contributor.register.store');
    Route::get('/admin/register', [RegisteredUserController::class, 'createAdmin'])->name('admin.register');
    Route::post('/admin/register', [RegisteredUserController::class, 'storeAdmin'])->middleware('throttle:6,1')->name('admin.register.store');
    Route::get('/registration/submitted', [RegisteredUserController::class, 'submitted'])->name('registration.submitted');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('verification.send');
    Route::get('/confirm-password', [ConfirmablePasswordController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.confirm.store');
});
