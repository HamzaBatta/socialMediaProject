<?php
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\GoogleAuthController;

use App\Http\Controllers\CommentController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// {====== Public Routes ======}
Route::post('/auth/google/token', [GoogleAuthController::class, 'handleGoogleToken']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/request-reset-code', [AuthController::class, 'requestResetCode']);
Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);

// {====== Public Read-Only Routes ======}
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{id}', [PostController::class, 'show']);

Route::get('/posts/{postId}/media', [MediaController::class, 'index']);
Route::get('/posts/{postId}/media/{mediaId}', [MediaController::class, 'show']);

Route::get('/comments', [CommentController::class, 'index']);  // pass post_id or comment_id
Route::get('/comments/{id}', [CommentController::class, 'show']);


// {====== Protected Routes (auth:api) ======}
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Posts
    Route::post('/posts', [PostController::class, 'store']);
    Route::post('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);

    // Media
    Route::post('/posts/{postId}/media', [MediaController::class, 'store']);
    Route::delete('/posts/{postId}/media/{mediaId}', [MediaController::class, 'destroy']);

    // Comments
    Route::post('/comments', [CommentController::class, 'store']);
    Route::post('/comments/{id}', [CommentController::class, 'update']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

    // Likes
    Route::post('/likes', [LikeController::class, 'toggle']);
});

require __DIR__ . '/Account/follow.php';

