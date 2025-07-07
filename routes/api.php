<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// {====== Public Routes ======}
Route::post('/auth/google/token', [GoogleAuthController::class, 'handleGoogleToken']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/verify-code', [AuthController::class, 'verifyCode']);
Route::post('/request-code', [AuthController::class, 'requestCode']);
Route::post('/verify-reset-code', [AuthController::class, 'verifyResetCode']);

// {====== Public Read-Only Routes ======}

//posts

Route::get('/posts/{id}', [PostController::class, 'show']);

//media
Route::get('/posts/{postId}/media', [MediaController::class, 'index']);
Route::get('/posts/{postId}/media/{mediaId}', [MediaController::class, 'show']);

//comments
Route::get('/comments', [CommentController::class, 'index']);  // pass post_id or comment_id
Route::get('/comments/{id}', [CommentController::class, 'show']);

//statuses
Route::get('/statuses', [StatusController::class, 'index']);
Route::get('/statuses/{id}', [StatusController::class, 'show']);


// {====== Protected Routes (auth:api) ======}
Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Posts
    Route::get('/posts', [PostController::class, 'index']);
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
    Route::get('/likes/users',[LikeController::class,'likedUsers']);

    //Statuses
    Route::post('/statuses',[StatusController::class,'store']);
    Route::post('/statuses/{id}', [StatusController::class, 'update']);
    Route::delete('/statuses/{id}', [StatusController::class, 'destroy']);

    //User
    Route::get('/user/check-username', [UserController::class, 'checkUsername']);
    Route::post('/user/update', [UserController::class, 'updateProfile']); // name, username, bio, avatar, privacy , personal_info
    Route::get('/user/{id}', [UserController::class, 'show']);
    Route::post('/user/changePassword',[UserController::class,'changePassword']);
    Route::post('/user/request-change-email-code', [UserController::class, 'requestChangeEmailCode']);
    Route::post('/user/verify-change-email-code', [UserController::class, 'verifyChangeEmailCode']);
    Route::delete('user/delete',[UserController::class,'destroy']);

    Route::get('followingWithStatus',[UserController::class,'followingWithStatus']);

    //Saved Collection
    Route::get('/saved-posts', [SavedPostController::class, 'index']);
    Route::post('/saved-posts/{id}', [SavedPostController::class, 'store']);
    Route::delete('/saved-posts/{id}', [SavedPostController::class, 'destroy']);
});

Route::middleware('auth:api')->prefix('groups')->group(function () {
    // List all groups
    Route::get('/', [GroupController::class, 'index']);

    // Create a new group
    Route::post('/', [GroupController::class, 'store']);

    // Show a single group
    Route::get('/{group}', [GroupController::class, 'show']);

    // Update a group (full or partial)
    Route::post( '/{group}', [GroupController::class, 'update']);

    // Delete a group
    Route::delete('/{group}', [GroupController::class, 'destroy']);

    // Join a group
    Route::post('/{group_id}/join', [GroupController::class, 'join']);

    // Leave a group
    Route::post('/{group_id}/leave', [GroupController::class, 'leave']);
    Route::get('/{group}/members', [GroupController::class, 'members']);
    Route::get('/{group}/requests', [GroupController::class, 'pendingRequests']);
    Route::post('/{group}/requests/respond', [GroupController::class, 'respondToRequest']);
});

Route::middleware('auth:api')->prefix('users')->group(function () {
    Route::post('/followUser', [FollowController::class, 'follow']);
    Route::post('/follow/request/{id}', [FollowController::class, 'respondToRequest']);
    Route::delete('/{user}/unfollow', [FollowController::class, 'unfollow']);
    Route::get('/{user}/followers', [FollowController::class, 'followers']);
    Route::get('/{user}/following', [FollowController::class, 'following']);
    Route::get('/requests', [RequestController::class, 'show'])
    ;
    Route::post('/block', [BlockController::class, 'block']);
    Route::post('/unblock', [BlockController::class, 'unblock']);
    Route::get('/blocked-users', [BlockController::class, 'blockedUsers']);
});



