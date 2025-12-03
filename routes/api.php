<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (require auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Media routes
    Route::post('/media', MediaController::class);
});
