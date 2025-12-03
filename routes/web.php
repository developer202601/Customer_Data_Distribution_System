<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExclusionUploadController;
use App\Http\Controllers\MasterDatasetUploadController;
use App\Http\Controllers\ProcessFileController;
use App\Http\Controllers\ProcessStatusController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BillRangeController;

Route::middleware('session.auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/master/upload', [MasterDatasetUploadController::class, 'create'])->name('master.upload.create');
    Route::post('/master/upload', [MasterDatasetUploadController::class, 'store'])->name('master.upload.store');
    Route::get('/process/upload', [ProcessFileController::class, 'create'])->name('process.upload.create');
    Route::post('/process/upload', [ProcessFileController::class, 'store'])->name('process.upload.store');
    Route::post('/process/upload/cancel', [ProcessFileController::class, 'cancel'])->name('process.upload.cancel');
    Route::get('/process/upload/progress/{token}', [ProcessFileController::class, 'progress'])->name('process.upload.progress');
    Route::get('/process/upload/complete/{token}', [ProcessFileController::class, 'complete'])->name('process.upload.complete');
    Route::get('/process/upload/preview', [ProcessFileController::class, 'preview'])->name('process.upload.preview');
    Route::get('/process/upload/vip', [ProcessFileController::class, 'vip'])->name('process.upload.vip');
    Route::get('/process/upload/rows', [ProcessFileController::class, 'rows'])->name('process.upload.rows');
    Route::get('/process/upload/export', [ProcessFileController::class, 'exportVip'])->name('process.upload.export');
    Route::get('/process/exclusions', [ExclusionUploadController::class, 'create'])->name('process.exclusions.create');
    Route::post('/process/exclusions', [ExclusionUploadController::class, 'store'])->name('process.exclusions.store');
    Route::get('/process/status', [ProcessStatusController::class, 'show'])->name('process.status.current');
    Route::get('/process/assignments', [AssignmentController::class, 'index'])->name('process.assignments.index');
    // Consolidated into overview; group-specific pages removed
    Route::get('/process/assignments/download/{group}/{bucket}', [AssignmentController::class, 'download'])->name('process.assignments.download');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/admin/adminconfig', [AdminController::class, 'config'])->name('admin.config');
    Route::post('/configurations/billrange', [BillRangeController::class, 'createRange'])->name('configurations.billrange');
    
    
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');

//Route::post('/create/range', [AdminController::class, 'createRange'])->name('create.range');
