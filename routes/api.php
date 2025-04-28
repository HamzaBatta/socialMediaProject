<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Mail\Emails;

use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Str;


Route::get('/send',function(){
    Mail::to('hamzatarek204@gmail.com')->send(new Emails);
    return response('sending');
});


Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');



Route::post('/verify-code', [AuthController::class, 'verifyCode']);


Route::post('/request-reset-code', [AuthController::class, 'requestResetCode']);
Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);


