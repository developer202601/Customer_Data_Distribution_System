<?php

use App\Http\Controllers\Api\CustomerApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/customers/uncalled-latest', [CustomerApiController::class, 'getUncalledLatestReportCustomers']);
});

