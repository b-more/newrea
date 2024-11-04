<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\SparkController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::post('/client_name', [App\Http\Controllers\TransactionController::class, 'check_customer']);
Route::post('/pay', [App\Http\Controllers\TransactionController::class, 'make_payment']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::post('/ussd', [App\Http\Controllers\UssdSessionController::class, 'handleussd']);

// Basic test route
Route::get('/test', [TestController::class, 'test']);

// SparkMeter route
Route::get('/spark/{code}', [SparkController::class, 'lookup']);

Route::get('/test', [TestController::class, 'test']);

// SparkMeter routes
Route::prefix('spark')->group(function () {
    Route::get('/customer/{code}', [SparkController::class, 'lookup']);
    Route::get('/meter/{number}/balance', [SparkController::class, 'balance']);
    Route::post('/token/generate', [SparkController::class, 'generateToken']);
});
