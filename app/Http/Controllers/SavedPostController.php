<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SavedPostController extends Controller
{
    public function index(Request $request)
    {
        $authUser = Auth::user();
        $page = $request->query('page', 1);
        $perPage = 10;

        $savedPost = $authUser->savedPost;

        if (!$savedPost) {
            return response()->json(['message' => 'No saved posts found.'], 404);
        }

        $query = $savedPost->posts()
                           ->with(['media', 'user.media'])
                           ->withCount(['likes', 'comments'])
                           ->latest();

        $posts = $query->paginate($perPage, ['*'], 'page', $page);

        $posts->getCollection()->transform(function ($post) use ($authUser,$savedPost) {
            $isFollowing = $authUser->isFollowing($post->user);
            $isSaved = $savedPost->isSaved($post->id) ? : false;
            return [
                'id' => $post->id,
                'text' => $post->text,
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'is_liked' => $post->isLikedBy($authUser->id),
                'group_id' => $post->group_id,
                'media' => $post->media->map(fn($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => url("storage/{$media->path}"),
                ]),
                'privacy' => $post->privacy,
                'is_saved' => $isSaved,
                'created_at' => $post->created_at,
                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'username' => $post->user->username,
                    'avatar' => $post->user->media
                        ? url("storage/{$post->user->media->path}")
                        : null,
                    'is_following' => $isFollowing,
                    'is_private' => $post->user->is_private,
                ],
            ];
        });

        return response()->json([
            'saved_posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'last_page' => $posts->lastPage(),
            ],
            'message' => 'Saved posts retrieved successfully.'
        ]);
    }

    public function store(Request $request,$post_id)
    {


        $user = Auth::user();
        $savedPost = $user->savedPost;

        if (!$savedPost) {
            $savedPost = $user->savedPost()->create();
        }

        $savedPost->posts()->syncWithoutDetaching([$post_id]);

        return response()->json(['message' => 'Post saved successfully.']);
    }

    public function destroy(Request $request,$post_id)
    {


        $user = Auth::user();
        $savedPost = $user->savedPost;

        if (!$savedPost) {
            return response()->json(['message' => 'Saved collection not found.'], 404);
        }

        $savedPost->posts()->detach($post_id);

        return response()->json(['message' => 'Post removed from saved.']);
    }
}
