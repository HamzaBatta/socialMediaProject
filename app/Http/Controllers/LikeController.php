<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Status;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Request as FollowRequest;
use App\Services\EventPublisher;
class LikeController extends Controller
{
    public function toggle(Request $request,FirebaseService $firebase)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'comment_id' => 'nullable|exists:comments,id',
            'status_id' => 'nullable|exists:statuses,id',
            'ad_id' => 'nullable',
        ]);

        $provided = array_filter([
            'post_id' => $request->post_id,
            'comment_id' => $request->comment_id,
            'status_id' => $request->status_id,
            'ad_id' => $request->ad_id
        ]);

        if (count($provided) !== 1) {
            return response()->json(['message' => 'Exactly one of post_id, comment_id, or status_id must be provided'], 422);
        }

        $authUser = Auth::user();
        $targetUser = null;
        $title = '';
        $body = '';
        $route = '';
        $details = [];

        // Determine the type and ID
        if ($request->post_id) {
            $likeableType = Post::class;
            $likeableId = $request->post_id;
            $post = Post::with('user.media')->findOrFail($likeableId);
            $targetUser = $post->user;
            $title = 'Post Update';
            $body = "{$authUser->name} liked your post";
            $route = '';
            $details = [
                'post_id' => $post->id,
                'personal_account_id' => $authUser->id,
                'other_account_id' => $targetUser->id
            ];
        } elseif ($request->comment_id) {
            $likeableType = Comment::class;
            $likeableId = $request->comment_id;
            $comment = Comment::with('user.media')->findOrFail($likeableId);
            $targetUser = $comment->user;
            $title = "{$authUser->name} liked your comment";
            $body = "$comment->text";
            $route = '';
            $details = [
                'comment_id' => $comment->id,
                'personal_account_id' => $authUser->id,
                'other_account_id' => $targetUser->id
            ];
        } elseif ($request->status_id) {
            $likeableType = Status::class;
            $likeableId = $request->status_id;
            $status = Status::with('user.media')->findOrFail($likeableId);
            $targetUser = $status->user;
            $title = 'Status Update';
            $body = "{$authUser->name} liked your status";
            $route = '';
            $details = [
                'status_id' => $status->id,
                'personal_account_id' => $authUser->id,
                'other_account_id' => $targetUser->id
            ];
        } elseif ($request->ad_id) {
            $likeableType = Ad::class;
            $likeableId = $request->ad_id;
        }

        $like = Like::where('user_id', $authUser->id)
                    ->where('likeable_type', $likeableType)
                    ->where('likeable_id', $likeableId)
                    ->first();

        if ($like) {

            if($likeableType === Post::class) {
                Post::where('id', $likeableId)->decrement('likes_count');
            }

            $like->delete();


            app(EventPublisher::class)->publishEvent('Unlike',[
                'id' => $authUser->id,
                'likeable_type' => $likeableType,
                'likeable_id'=> $likeableId
        ]);

            return response()->json(['message' => 'Unliked successfully'], 200);
        } else {

            if($likeableType === Post::class) {
                Post::where('id', $likeableId)->increment('likes_count');
            }

            Like::create([
                'user_id' => $authUser->id,
                'likeable_type' => $likeableType,
                'likeable_id' => $likeableId,
            ]);

            //send notification only if target user has a device token and is not the liker
            if ($targetUser && $targetUser->id !== $authUser->id && $targetUser->device_token) {
                $firebase->sendStructuredNotification(
                    $targetUser->device_token,
                    $title,
                    $body,
                    $route,
                    $details,
                    $authUser->media ? url("storage/{$authUser->media->path}") : null
                );
            }

        app(EventPublisher::class)->publishEvent('Like',[
                'id' => $authUser->id,
                'likeable_type' => $likeableType,
                'likeable_id'=> $likeableId
        ]);


            return response()->json(['message' => 'Liked successfully'], 201);
        }
    }

    public function likedUsers(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'status_id' => 'nullable|exists:statuses,id',
            'ad_id' => 'nullable',
        ]);

        if (!$request->post_id && !$request->status_id && !$request->ad_id) {
            return response()->json(['error' => 'At least one ID is required'], 422);
        }

        $likeableType = match (true) {
            $request->ad_id => Ad::class,
            $request->post_id => Post::class,
            default => Status::class,
        };
        $likeableId = $request->ad_id ?? $request->post_id ?? $request->status_id;

        $authUser = Auth::user();
        $excludedIds = $authUser->blockedUsers()->pluck('users.id')
                                ->merge($authUser->blockedByUsers()->pluck('users.id'));

        $likes = Like::with('user.media')
                     ->where('likeable_type', $likeableType)
                     ->where('likeable_id', $likeableId)
                     ->whereHas('user', fn($query) => $query->whereNotIn('id', $excludedIds))
                     ->get();

        $users = $likes->map(function ($like) use ($authUser) {
            $user = $like->user;
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
                'is_requested' => $isRequested,
            ];
        });

        return response()->json(['users' => $users]);
    }
}
