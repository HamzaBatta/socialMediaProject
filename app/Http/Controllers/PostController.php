<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $authUser = auth()->id();

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

        return response()->json(['posts' => $posts]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string',
        ]);

        $post = Post::create([
            'user_id' => auth()->id(),
            'text' => $request->text,
        ]);

        return response()->json(['message' => 'Post created successfully', 'post_id' => $post->id], 201);
    }

    public function show($id, Request $request)
    {
        $authUser = auth()->id();

        $post = Post::with(['media'])
                    ->withCount(['likes', 'comments'])
                    ->findOrFail($id);

        return response()->json([
            'id' => $post->id,
            'text' => $post->text,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'is_liked' => $post->isLikedBy($authUser),
            'media' => $post->media->map(function ($media) {
                return [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => url("storage/{$media->path}"),
                ];
            }),
            'created_at' => $post->created_at,
        ]);
    }

    public function update(Request $request, $id)
    {
        $post = Post::where('user_id', auth()->id())->findOrFail($id);

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
        $post = Post::where('user_id', auth()->id())->findOrFail($id);

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
