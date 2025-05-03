<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;



class FollowController extends Controller
{



    public function follow(Request $request)
    {
        $currentUser = User::find($request->input('senderId'));
        $targetUser = User::find($request->input('targetId'));
        // 2. Prevent self-follow
        
        if ($currentUser->id === $targetUser->id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 400);
        }
    
        // 3. Attach if not already following
        if (! $currentUser->isFollowing($targetUser)) {
            try {
                $currentUser->following()->attach($targetUser->id);
            } catch (\Illuminate\Database\QueryException $e) {
                // Dump the SQL error code & message:
                dd($e->getCode(), $e->getMessage());
            }
            return response()->json(['message' => 'User followed successfully.'], 200);
        }
    
        // 4. Already following
        return response()->json(['message' => 'You are already following this user.'], 409);
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
