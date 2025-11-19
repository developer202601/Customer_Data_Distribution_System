<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExclusionUploadController;
use App\Http\Controllers\ProcessFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('session.auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/process/upload', [ProcessFileController::class, 'create'])->name('process.upload.create');
    Route::post('/process/upload', [ProcessFileController::class, 'store'])->name('process.upload.store');
    Route::post('/process/upload/cancel', [ProcessFileController::class, 'cancel'])->name('process.upload.cancel');
    Route::get('/process/upload/progress/{token}', [ProcessFileController::class, 'progress'])->name('process.upload.progress');
    Route::get('/process/upload/complete/{token}', [ProcessFileController::class, 'complete'])->name('process.upload.complete');
    Route::get('/process/upload/preview', [ProcessFileController::class, 'preview'])->name('process.upload.preview');
    Route::get('/process/upload/vip', [ProcessFileController::class, 'vip'])->name('process.upload.vip');
    Route::get('/process/upload/export', [ProcessFileController::class, 'exportVip'])->name('process.upload.export');
    Route::get('/process/exclusions', [\App\Http\Controllers\ExclusionUploadController::class, 'create'])->name('process.exclusions.create');
    Route::post('/process/exclusions', [\App\Http\Controllers\ExclusionUploadController::class, 'store'])->name('process.exclusions.store');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
