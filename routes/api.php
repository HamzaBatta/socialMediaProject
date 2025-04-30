<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;





Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');



Route::post('/verify-code', [AuthController::class, 'verifyCode']);


Route::post('/request-reset-code', [AuthController::class, 'requestResetCode']);
Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);

// {======Posts======}
Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class, 'store']);
Route::get('/posts/{id}', [PostController::class, 'show']);
Route::post('/posts/{id}', [PostController::class, 'update']);
Route::delete('/posts/{id}', [PostController::class, 'destroy']);

// {======Media======}
Route::get('/posts/{postId}/media', [MediaController::class, 'index']);
Route::post('/posts/{postId}/media', [MediaController::class, 'store']);
Route::get('/posts/{postId}/media/{mediaId}', [MediaController::class, 'show']);
Route::delete('/posts/{postId}/media/{mediaId}', [MediaController::class, 'destroy']);

// {======Comments======}
Route::get('/comments', [CommentController::class, 'index']); // pass post_id or comment_id in the
Route::post('/comments', [CommentController::class, 'store']);
Route::get('/comments/{id}', [CommentController::class, 'show']);
Route::post('/comments/{id}', [CommentController::class, 'update']);
Route::delete('/comments/{id}', [CommentController::class, 'destroy']);


