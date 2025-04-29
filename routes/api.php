<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Mail\Emails;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;


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


Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class, 'store']);
Route::get('/posts/{id}', [PostController::class, 'show']);
Route::post('/posts/{id}', [PostController::class, 'update']);
Route::delete('/posts/{id}', [PostController::class, 'destroy']);

Route::get('/posts/{postId}/media', [MediaController::class, 'index']);
Route::post('/posts/{postId}/media', [MediaController::class, 'store']);
Route::get('/posts/{postId}/media/{mediaId}', [MediaController::class, 'show']);
Route::delete('/posts/{postId}/media/{mediaId}', [MediaController::class, 'destroy']);

