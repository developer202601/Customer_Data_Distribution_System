<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProcessFileController;
use Illuminate\Support\Facades\Route;

Route::middleware('session.auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/process/upload', [ProcessFileController::class, 'create'])->name('process.upload.create');
    Route::post('/process/upload', [ProcessFileController::class, 'store'])->name('process.upload.store');
    Route::get('/process/upload/preview', [ProcessFileController::class, 'preview'])->name('process.upload.preview');
    Route::get('/process/upload/export', [ProcessFileController::class, 'exportVip'])->name('process.upload.export');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
