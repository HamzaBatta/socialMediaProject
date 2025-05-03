<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    public function follow(Request $request)
    {
        $targetUserId = $request->route('user');
        $targetUser = User::findOrFail($targetUserId);
        $currentUser =User::findOrFail($targetUserId);

        if ($currentUser->id === $targetUser->id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 400);
        }

        if (!$currentUser->isFollowing($targetUser)) {
            $currentUser->following()->attach($targetUser->id);
            return response()->json(['message' => 'User followed successfully.']);
        }

        return response()->json(['message' => 'You are already following this user.'], 400);
    }

    public function unfollow(Request $request)
    {
        $targetUserId = $request->route('user');
        $targetUser = User::findOrFail($targetUserId);
        $currentUser = User::findOrFail($targetUserId);

        if ($currentUser->isFollowing($targetUser)) {
            $currentUser->following()->detach($targetUser->id);
            return response()->json(['message' => 'User unfollowed successfully.']);
        }

        return response()->json(['message' => 'You are not following this user.'], 400);
    }

    public function followers(Request $request)
    {
        $targetUserId = $request->route('user');
        $targetUser = User::findOrFail($targetUserId);
        $followers = $targetUser->followers()->get();
        return response()->json($followers);
    }

    public function following(Request $request)
    {
        $targetUserId = $request->route('user');
        $targetUser = User::findOrFail($targetUserId);
        $following = $targetUser->following()->get();
        return response()->json($following);
    }
}
