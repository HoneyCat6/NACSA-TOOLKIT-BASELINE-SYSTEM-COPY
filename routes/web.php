<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\TrendController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,15');

    Route::get('/otp', [AuthController::class, 'showOtp'])->name('otp.show');
    Route::post('/otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:5,15')->name('otp.verify');
    Route::post('/otp/resend', [AuthController::class, 'resendOtp'])->middleware('throttle:3,15')->name('otp.resend');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:3,15')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware(['auth', 'mfa'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password/change', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/password/change', [AuthController::class, 'changePassword'])->name('password.change.update');

    Route::middleware('password.fresh')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::middleware('role:compliance_officer')->group(function () {
            Route::get('/assessments/upload', [AssessmentController::class, 'showUpload'])->name('assessments.upload');
            Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
            Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
        });

        Route::middleware('role:compliance_officer,executive_reviewer')->group(function () {
            Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
            Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
            Route::get('/trends', [TrendController::class, 'index'])->name('trends.index');
        });
    });
});
