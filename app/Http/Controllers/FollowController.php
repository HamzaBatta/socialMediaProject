<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;



class FollowController extends Controller
{



    public function follow(Request $request)
    {
        $currentUser = User::find(Auth::id());
        $targetUser = User::find($request->input('targetId'));
        
        if ($currentUser->id === $targetUser->id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 400);
        }
    
        // handle the case if we are not following the user already
        if (! $currentUser->isFollowing($targetUser)) {
            // handle the case of a private account 
            // 1- create the request to the other user
            if($targetUser->is_private){
                $existingRequest = $targetUser->requests()
                                            ->where('user_id', $currentUser->id)
                                            ->where('state', 'pending')
                                            ->first();
                                                    
                if (!$existingRequest) {
                    $request = $targetUser->requests()->create([
                        'user_id' => $currentUser->id,
                        'requested_at' => now(),
                    ]);
                    return response()->json(['message' => 'sent a follow request'], 200);
                }else {
                    $existingRequest->delete();
                    return response()->json(['message' => 'unsent the follow request'], 200);
                }
                 
            }


            try {
                $currentUser->following()->attach($targetUser->id);
            } catch (\Illuminate\Database\QueryException $e) {
                // Dump the SQL error code & message:
                return response()->json(['message' => 'there is a database error'], 400);
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
        $currentUser =  User::find(Auth::id());

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
