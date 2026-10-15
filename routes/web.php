<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ReportController;
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

        Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/users/{user}', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('/users/{user}/reactivate', [UserController::class, 'reactivate'])->name('users.reactivate');
            Route::post('/users/{user}/password-reset', [UserController::class, 'sendPasswordReset'])
                ->middleware('throttle:6,1')
                ->name('users.password-reset');

            Route::get('/audit-logs', [AuditLogController::class, 'technical'])->name('audit-logs.index');

            Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
        });

        Route::middleware('role:compliance_officer')->group(function () {
            Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
            Route::get('/assessments/upload', [AssessmentController::class, 'showUpload'])->name('assessments.upload');
            Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
            Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
            Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('assessments.destroy');
            Route::post('/alerts/acknowledge-all', [AlertController::class, 'acknowledgeAll'])->name('alerts.acknowledge-all');
            Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge'])->name('alerts.acknowledge');
            Route::post('/reports/{assessment}/generate', [ReportController::class, 'generate'])->name('reports.generate');
        });

        Route::middleware('role:compliance_officer,executive_reviewer')->group(function () {
            Route::get('/trends', [TrendController::class, 'index'])->name('trends.index');
            Route::get('/heatmap', [HeatmapController::class, 'index'])->name('heatmap.index');
            Route::get('/heatmap/element/{code}', [HeatmapController::class, 'detail'])->name('heatmap.detail');
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
        });
    });
});
