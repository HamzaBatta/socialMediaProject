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

    public function store(Request $request)
    {
        $request->validate([
            'text'      => 'nullable|string',
            'group_id'  => 'nullable|exists:groups,id',
            'media'     => 'nullable|array',
            'media.*'   => 'file|mimes:jpeg,png,gif,mp4,mov|max:20480',
            'privacy'   => ['required', 'in:public,private'],
        ]);

        $post = Post::create([
            'user_id' => Auth::id(),
            'text' => $request->text,
            'group_id' => $request->group_id,
            'privacy' =>$request->privacy
        ]);

        $mediaFiles = $request->file('media', []);

        foreach ((array) $mediaFiles as $file) {
            try {
                $path = $file->store('posts', 'public');
                $type = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';
                $media = $post->media()->create([
                    'path' => $path,
                    'type' => $type,
                ]);
            } catch (\Exception $e) {
                $post->delete();
                $post->media()->delete();
                return response()->json([
                    'message' => 'Post not created'
                ], 400);
            }
        }

        return response()->json([
            'message' => 'Post created successfully',
            'post' => [
                'id' => $post->id,
                'text' => $post->text,
                'group_id' => $post->group_id,
                'media' => $post->media->map(function ($media) {
                    return [
                        'id' => $media->id,
                        'type' => $media->type,
                        'url' => url("storage/{$media->path}"),
                    ];
                }),
                'privacy' =>$post->privacy,
                'created_at' => $post->created_at,
            ],
        ], 201);
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
        ]);
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
