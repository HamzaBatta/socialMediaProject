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
        $user = Auth::user();

        $savedPost = $user->savedPost;

        if (!$savedPost) {
            return response()->json(['message' => 'No saved posts found.'], 404);
        }

        $posts = $savedPost->posts()->with('media','user')->get()->map(function ($post) {
            $user = Auth::user();
            return [
                'id' => $post->id,
                'text' => $post->text,
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'is_liked' => $post->isLikedBy($user->id),
                'group_id' => $post->group_id,
                'media' => $post->media->map(fn($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => url("storage/{$media->path}"),
                ]),
                'privacy' => $post->privacy,
                'created_at' => $post->created_at,
                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'avatar' => $post->user->media
                        ? url("storage/{$post->user->media->path}")
                        : null,
                ],
            ];
        });

        return response()->json([
            'saved_posts' => $posts,
            'message' => 'Saved posts retrieved successfully.'
        ],200);
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
