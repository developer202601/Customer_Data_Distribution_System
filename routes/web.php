<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProcessFileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BillRangeController;

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
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/admin/adminconfig', [AdminController::class, 'config'])->name('admin.config');
    Route::post('/configurations/billrange', [BillRangeController::class, 'createRange'])->name('configurations.billrange');
    
    Route::post('/configurations/billrange2', [BillRangeController::class, 'createStaff'])->name('configurations.billarears');
    
    
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');

//Route::post('/create/range', [AdminController::class, 'createRange'])->name('create.range');
