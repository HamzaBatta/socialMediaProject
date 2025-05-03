<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function index($postId)
    {
        $post = Post::findOrFail($postId);

        $mediaItems = $post->media()->get()->map(function ($media) {
            return [
                'id' => $media->id,
                'type' => $media->type,
                'url' => url("storage/{$media->path}"),
                'created_at' => $media->created_at,
            ];
        });

        return response()->json([
            'message' => 'Media retrieved successfully.',
            'media' => $mediaItems,
        ]);
    }

    public function store(Request $request, $postId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'media' => 'required|file|mimes:jpeg,png,jpg,mp4,mov,avi|max:20480',
        ]);

        $type = str_starts_with($request->file('media')->getMimeType(), 'video') ? 'video' : 'image';

        $path = $request->file('media')->store('media', 'public');

        $media = $post->media()->create([
            'type' => $type,
            'path' => $path,
        ]);

        return response()->json([
            'message' => 'Media uploaded successfully.',
            'id' => $media->id,
            'type' => $media->type,
            'url' => url("storage/{$media->path}"),
        ], 201);
    }

    public function show($postId, $mediaId)
    {
        $media = Media::where('post_id', $postId)->where('id', $mediaId)->first();

        if (!$media) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        return response()->json([
            'id' => $media->id,
            'type' => $media->type,
            'url' => url("storage/{$media->path}"),
            'created_at' => $media->created_at,
        ]);
    }

    public function destroy($postId, $mediaId)
    {
        $post = Post::findOrFail($postId);

        if ($post->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $media = $post->media()->where('id', $mediaId)->first();

        if (!$media) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        if (Storage::disk('public')->exists($media->path)) {
            Storage::disk('public')->delete($media->path);
        }

        $media->delete();

        return response()->json(['message' => 'Media deleted successfully.']);
    }
}
