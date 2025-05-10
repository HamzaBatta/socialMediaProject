<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $authUser = Auth::user();
        $targetUser = User::findOrFail($request->user_id);

        $query = Post::query()
                     ->where('user_id', $targetUser->id)
                     ->with(['media'])
                     ->withCount(['likes', 'comments']);

        // If not the same user
        if ($authUser->id !== $targetUser->id) {
            if ($authUser->isFollowing($targetUser)) {
                // show all
            } elseif (!$targetUser->is_private) {
                $query->where('privacy', 'public');
            } else {
                return response()->json(['posts' => []]);
            }
        }else{ // if the user requesting his own posts
            $posts = Post::where('user_id', $request->user_id)
                     ->with(['media'])
                     ->withCount(['likes', 'comments'])
                     ->latest()
                     ->get()
                     ->map(function ($post) use ($authUser) {
                         return [
                             'id' => $post->id,
                             'text' => $post->text,
                             'likes_count' => $post->likes_count,
                             'comments_count' => $post->comments_count,
                             'is_liked' => $post->isLikedBy($authUser),
                             'group_id' =>$post->group_id,
                             'media' => $post->media->map(function ($media) {
                                 return [
                                     'id' => $media->id,
                                     'type' => $media->type,
                                     'url' => url("storage/{$media->path}"),
                                 ];
                             }),
                             'created_at' => $post->created_at,
                         ];
                     });

        return response()->json(['posts' => $posts ]); 
        }

        $posts = $query->latest()->get()->map(function ($post) use ($authUser) {
            return [
                'id' => $post->id,
                'text' => $post->text,
                'likes_count' => $post->likes()->count(),
                'comments_count' => $post->comments()->count(),
                'is_liked' => $post->isLikedBy($authUser->id),
                'group_id' => $post->group_id,
                'media' => $post->media->map(fn($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => url("storage/{$media->path}"),
                ]),
                'privacy' =>$post->privacy,
                'created_at' => $post->created_at,
            ];
        });

        return response()->json(['posts' => $posts]);
    }


    public function show($id, Request $request)
    {
        $authUser = Auth::user();
        $post = Post::with(['media', 'user'])->withCount(['likes', 'comments'])->findOrFail($id);

        if (!Gate::allows('view', $post)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
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
            'privacy' =>$post->privacy,
            'created_at' => $post->created_at,
        ]) ;
    }

    public function update(Request $request, $id)
    {
        $post = Post::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'text' => 'nullable|string',
        ]);

        $post->update([
            'text' => $request->text,
        ]);

        return response()->json(['message' => 'Post updated successfully']);
    }

    public function destroy($id)
    {
        $post = Post::where('user_id', Auth::id())->findOrFail($id);

        foreach ($post->media as $media) {
            if (Storage::disk('public')->exists($media->path)) {
                Storage::disk('public')->delete($media->path);
            }
            $media->delete();
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully']);
    }
}
