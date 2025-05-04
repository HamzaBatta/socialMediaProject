<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
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

        $authUser = Auth::id();

        $user = User::find($request->user_id);
        // this line will authorize the user in one of three cases 
        // Case 1: Public user profile 
        // Case 2: Private user but user is the owner
        // Case 3: Private user and user follows the owner
        $this->authorize('viewAny',$authUser, $user);

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

        return response()->json(['posts' => $posts]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string',
            'group_id' => 'nullable|exists:groups,id',
            'privacy' => 'required|in:public,private',
            'media' => 'nullable',
        ]);

        $post = Post::create([
            'user_id' => Auth::id(),
            'text' => $request->text,
            'group_id' => $request->group_id,
            'privacy' => $request->privacy,
        ]);

        $mediaFiles = $request->allFiles('media');

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
            'post_id' => $post->id,
        ], 201);
    }

    public function show($id, Request $request)
    {
        $authUser = Auth::id();

        $post = Post::with(['media'])
                    ->withCount(['likes', 'comments'])
                    ->findOrFail($id);

        // this line will authorize the user in one of three cases 
        // Case 1: Public post
        // Case 2: Private post but user is the owner
        // Case 3: Private post and user follows the owner
        $this->authorize('view',$authUser, $post);

        return response()->json([
            'id' => $post->id,
            'text' => $post->text,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'is_liked' => $post->isLikedBy($authUser),
            'group_id' => $post->group_id,
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
