<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExclusionUploadController;
use App\Http\Controllers\MasterDatasetUploadController;
use App\Http\Controllers\ProcessFileController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/process/assignments', [AssignmentController::class, 'index'])->name('process.assignments.index');
    Route::get('/process/assignments/group-a', [AssignmentController::class, 'groupA'])->name('process.assignments.group-a');
    Route::get('/process/assignments/group-b', [AssignmentController::class, 'groupB'])->name('process.assignments.group-b');
    Route::get('/process/assignments/exclusions', [AssignmentController::class, 'exclusions'])->name('process.assignments.exclusions');
    Route::get('/process/assignments/vip', [AssignmentController::class, 'vip'])->name('process.assignments.vip');
    Route::get('/process/assignments/region-billing', [AssignmentController::class, 'regionBilling'])->name('process.assignments.region');
    Route::get('/process/assignments/filtered-out', [AssignmentController::class, 'filteredOut'])->name('process.assignments.filtered-out');
    Route::post('/process/assignments/regenerate', [AssignmentController::class, 'regenerate'])->name('process.assignments.regenerate');
    Route::get('/process/assignments/download/{group}/{bucket}', [AssignmentController::class, 'download'])->name('process.assignments.download');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.perform');
