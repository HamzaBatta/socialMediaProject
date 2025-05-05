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
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
        ], [], [
            'user_id' => 'user_id',
        ]);

        $authUser = Auth::id();
        $authUser = User::find($authUser);
        $authorize = false;
        $message = 'i did not enter any conditions';
        $user = User::find($validatedData['user_id']);
        $authorize =  $authUser->isFollowing($user);
        $message = $authorize;
        // this line will authorize the user in one of three cases 
        // Case 1: Public user profile 
        // Case 2: Private user but user is the owner
        // Case 3: Private user and user follows the owner
        //$this->authorize('viewAny',$authUser, $user);
        if (!$user->is_private) {
            $authorize = true;
            $message = 'the user is public';
        }
    
        // Case 2: Profile is private but user is viewing their own profile
        if ($user->id === $authUser->id) {
            $authorize = true;
            $message = 'you are asking yourself';
        }
    
        // Case 3: Profile is private and requesting user follows the profile owner
        if(!$authorize){
            return response()->json(['message' => $message],403);
        }

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

        return response()->json(['posts' => $posts , 'message' => $message]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text'      => 'nullable|string',
            'group_id'  => 'nullable|exists:groups,id',
            'media'     => 'nullable|array',        
            'media.*'   => 'file|mimes:jpeg,png,gif,mp4,mov|max:20480',
        ]);

        $post = Post::create([
            'user_id' => Auth::id(),
            'text' => $request->text,
            'group_id' => $request->group_id,
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
                'created_at' => $post->created_at,
            ],
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
