<?php

use App\Http\Controllers\AuthController;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleAuthController;







Route::post('/auth/google/token', [GoogleAuthController::class, 'handleGoogleToken']);


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');



Route::post('/verify-code', [AuthController::class, 'verifyCode']);


Route::post('/request-reset-code', [AuthController::class, 'requestResetCode']);
Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);
