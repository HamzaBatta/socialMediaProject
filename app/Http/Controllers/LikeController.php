<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Status;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Request as FollowRequest;

class LikeController extends Controller
{
    public function toggle(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'comment_id' => 'nullable|exists:comments,id',
            'status_id' => 'nullable|exists:statuses,id',
        ]);

        $provided = array_filter([
            'post_id' => $request->post_id,
            'comment_id' => $request->comment_id,
            'status_id' => $request->status_id,
        ]);

        if (count($provided) !== 1) {
            return response()->json(['message' => 'Exactly one of post_id, comment_id, or status_id must be provided'], 422);
        }

        $user = auth()->user();

        // Determine the type and ID
        if ($request->post_id) {
            $likeableType = Post::class;
            $likeableId = $request->post_id;
        } elseif ($request->comment_id) {
            $likeableType = Comment::class;
            $likeableId = $request->comment_id;
        } else {
            $likeableType = Status::class;
            $likeableId = $request->status_id;
        }

        $like = Like::where('user_id', $user->id)
                    ->where('likeable_type', $likeableType)
                    ->where('likeable_id', $likeableId)
                    ->first();

        if ($like) {
            $like->delete();
            return response()->json(['message' => 'Unliked successfully'], 200);
        } else {
            Like::create([
                'user_id' => $user->id,
                'likeable_type' => $likeableType,
                'likeable_id' => $likeableId,
            ]);
            return response()->json(['message' => 'Liked successfully'], 201);
        }
    }

    public function likedUsers(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'status_id' => 'nullable|exists:statuses,id',
        ]);

        if (!$request->post_id && !$request->status_id) {
            return response()->json(['message' => 'post_id or status_id is required'], 422);
        }

        if ($request->post_id && $request->status_id) {
            return response()->json(['message' => 'Only one of post_id or status_id should be provided'], 422);
        }

        $likeableType = $request->post_id ? Post::class : Status::class;
        $likeableId = $request->post_id ?? $request->status_id;

        $authUser = Auth::user();

        // Get blocked and blocking user IDs
        $blockedUserIds = $authUser->blockedUsers()->pluck('users.id');
        $blockedByUserIds = $authUser->blockedByUsers()->pluck('users.id');
        $excludedIds = $blockedUserIds->merge($blockedByUserIds);

        // Fetch likes excluding blocked users
        $likes = Like::with('user.media')
                     ->where('likeable_type', $likeableType)
                     ->where('likeable_id', $likeableId)
                     ->whereHas('user', function ($query) use ($excludedIds) {
                         $query->whereNotIn('id', $excludedIds);
                     })
                     ->get();

        $users = $likes->map(function ($like) use ($authUser) {
            $user = $like->user;
            $isOwner = $authUser->id === $user->id;
            $isFollowing = $authUser->isFollowing($user);
            $isRequested = false;
            $isRequested = FollowRequest::where('user_id',$authUser->id)
                                        ->where('requestable_type',User::class)
                                        ->where('requestable_id',$user->id)
                                        ->where('state','pending')
                                        ->exists();

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

        return response()->json(['users' => $users]);
    }
}
