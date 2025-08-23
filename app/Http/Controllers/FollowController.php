<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use App\Models\Request as FollowRequest;    // your Eloquent model, aliased
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\EventPublisher;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;

class FollowController extends Controller
{



    public function follow(Request $request,FirebaseService $firebase)
    {
        $currentUser = User::findOrFail(Auth::id());
        $targetUser = User::findOrFail($request->targetId);

        if ($currentUser->id === $targetUser->id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 400);
        }

        // handle the case if we are not following the user already
        if (! $currentUser->isFollowing($targetUser)) {
            // handle the case of a private account
            // 1- create the request to the other user
            if($targetUser->is_private){
                $existing = $targetUser->requests()
                                   ->where('user_id', $currentUser->id)
                                   ->where('state', 'pending')
                                   ->first();

                if (! $existing) {
                    $targetUser->requests()->create([
                        'user_id'      => $currentUser->id,
                        'requested_at' => now(),
                    ]);
                    try{
                        // 🔔 Send follow request notification
                        if ($targetUser->device_token) {
                            $firebase->sendStructuredNotification(
                                $targetUser->device_token,
                                'New Follow Request',
                                "{$currentUser->name} requested to follow you",
                                '/home',
                                [
                                    'tab_index' => '3'
                                ],
                                $currentUser->media ? url("storage/{$currentUser->media->path}") : null
                            );
                        }
                    }catch(Exception $e){
                        Log::error('Failed to send notification', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                    return response()->json(['message' => 'Follow request sent.'], 200);
                }

                $existing->delete();
                return response()->json(['message' => 'Follow request withdrawn.'], 200);

            }


            try {
                $currentUser->following()->attach($targetUser->id);
            } catch (\Illuminate\Database\QueryException $e) {
                // Dump the SQL error code & message:
                return response()->json(['message' => 'there is a database error'], 400);
            }

            try{
                // 🔔 Send follow notification
                if($targetUser->device_token){
                    $firebase->sendStructuredNotification(
                        $targetUser->device_token,
                        'New Follower',
                        "{$currentUser->name} started following you",
                        '/other-account-page',
                        [
                            'personal_account_id' => $targetUser->id,
                            'other_account_id' => $currentUser->id
                        ],
                        $currentUser->media ? url("storage/{$currentUser->media->path}") : null
                    );
                }
            }catch(Exception $e){
                Log::error('Failed to send notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            app(EventPublisher::class)->publishEvent('UserFollowed',[
                'id' => $currentUser->id,
                'target' => $targetUser->id,
                ]);


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
        app(EventPublisher::class)->publishEvent('UserUnFollow',[
                'id' => $currentUser->id,
                'target' => $targetUser->id,
        ]);


        return response()->json(['message' => 'You are not following this user.'], 400);
    }

    public function followers(Request $request)
    {
        $authUser = Auth::user();
        $targetUserId = $request->route('user');
        $targetUser = User::findOrFail($targetUserId);

        //get list of user IDs that are blocked or blocked you
        $blockedUserIds = $authUser->blockedUsers()->pluck('users.id');
        $blockedByUserIds = $authUser->blockedByUsers()->pluck('users.id');
        $excludedIds = $blockedUserIds->merge($blockedByUserIds);



        $followers = $targetUser->followers()
                                ->whereNotIn('users.id', $excludedIds)
                                ->with('media')
                                ->get()
                                ->map(function ($user) use ($authUser) {
                                    $isOwner = $authUser->id === $user->id;
                                    $isFollowing = $authUser->isFollowing($user);

                                    $isRequested = FollowRequest::isRequested($authUser->requests(),$authUser->id,User::class,$user->id);
                                    return [
                                        'id' => $user->id,
                                        'name' => $user->name,
                                        'username' => $user->username,
                                        'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
                                        'is_private' => $user->is_private,
                                        'is_following' => $isOwner ? 'owner' : $isFollowing,
                                        'is_requested' => $isRequested
                                    ];
                                });

        return response()->json(['followers' => $followers]);
    }

    public function following(Request $request)
    {
        $authUser = Auth::user();
        $targetUserId = $request->route('user');
        $targetUser = User::findOrFail($targetUserId);

        //get list of user IDs that are blocked or blocked you
        $blockedUserIds = $authUser->blockedUsers()->pluck('users.id');
        $blockedByUserIds = $authUser->blockedByUsers()->pluck('users.id');
        $excludedIds = $blockedUserIds->merge($blockedByUserIds);

        $following = $targetUser->following()
                                ->whereNotIn('users.id', $excludedIds)
                                ->with('media')
                                ->get()
                                ->map(function ($user) use ($authUser) {
                                    $isOwner = $authUser->id === $user->id;
                                    $isFollowing = $authUser->isFollowing($user);
                                    $isRequested = FollowRequest::isRequested($authUser->requests(),$authUser->id,User::class,$user->id);
                                    return [
                                        'id' => $user->id,
                                        'name' => $user->name,
                                        'username' => $user->username,
                                        'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
                                        'is_private' => $user->is_private,
                                        'is_following' => $isOwner ? 'owner' : $isFollowing,
                                        'is_requested' => $isRequested
                                    ];
                                });

        return response()->json(['following' => $following]);
    }

    /**
     * Accept a pending follow request and create the follow pivot.
     *
     * @param  \Illuminate\Http\Request  $httpRequest
     * @param  int  $requestId
     * @return \Illuminate\Http\JsonResponse
     */
    public function respondToRequest(Request $httpRequest, $requestId,FirebaseService $firebase)
    {
        $currentUser = Auth::user();

        $state = $httpRequest->input('state');

        if (!in_array($state, ['approved', 'rejected'])) {
            return response()->json(['message' => 'Invalid action. Use approved or rejected.'], 422);
        }

        $followRequest = FollowRequest::where('id', $requestId)
                                      ->where('requestable_type', User::class)
                                      ->where('requestable_id', $currentUser->id)
                                      ->where('state', 'pending')
                                      ->firstOrFail();

        if ($state === 'approved') {
            $followRequest->update([
                'state'        => 'approved',
                'responded_at' => now(),
            ]);

            $requestingUser = User::findOrFail($followRequest->user_id);
            $requestingUser->following()->attach($currentUser->id);

            app(EventPublisher::class)->publishEvent('UserFollowed',[
                    'id' => $currentUser->id,
                    'target' => $requestingUser->id,
            ]);

            try{
                // 🔔 Send follow notification
                if($requestingUser->device_token){
                    $firebase->sendStructuredNotification(
                        $requestingUser->device_token,
                        'New Follower',
                        "{$currentUser->name} started following you",
                        '/other-account-page',
                        [
                            'personal_account_id' => $requestingUser->id,
                            'other_account_id' => $currentUser->id
                        ],
                        $currentUser->media ? url("storage/{$currentUser->media->path}") : null
                    );
                }
            }catch(Exception $e){
                Log::error('Failed to send notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }


            return response()->json(['message' => 'Follow request approved.'], 200);
        }

        //reject
        $followRequest->update([
            'state'        => 'rejected',
            'responded_at' => now(),
        ]);

        return response()->json(['message' => 'Follow request rejected.'], 200);
    }

}
