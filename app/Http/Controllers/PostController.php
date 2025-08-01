<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\EventPublisher;


class PostController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $authUser = Auth::user();
        $targetUser = User::with('media')->findOrFail($request->user_id);
        $page = $request->query('page', 1);
        $perPage = 10;

        $query = Post::query()
                     ->where('user_id', $targetUser->id)
                     ->with(['media', 'user.media'])
                     ->withCount(['likes', 'comments']);

        if ($authUser->id !== $targetUser->id) {
            if ($authUser->isFollowing($targetUser)) {
                // show all
            } elseif (!$targetUser->is_private) {
                $query->where('privacy', 'public');
            } else {
                return response()->json([
                    'posts' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                    ],
                ]);
            }
        }

        $posts = $query->latest()->paginate($perPage, ['*'], 'page', $page);

        $posts->getCollection()->transform(function ($post) use ($authUser) {
            $savedPost = $authUser->savedPost;
            $isSaved = $savedPost ? $savedPost->isSaved($post->id) : false;
            $isFollowing = $authUser->isFollowing($post->user);
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
                    'is_private' => $post->user->is_private
                ],
            ];
        });

        return response()->json([
            'posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'last_page' => $posts->lastPage(),
            ],
        ]);
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
        $authUser = Auth::user();
        $isFollowing = $authUser->isFollowing($post->user);

        app(EventPublisher::class)->publishEvent('PostCreated',[
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
                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'username' => $post->user->username,
                    'avatar' => $post->user->media
                        ? url("storage/{$post->user->media->path}")
                        : null,
                    'is_following' => $isFollowing,
                    'is_private' => $post->user->is_private
                ]
        ]);
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
                'user' => [
                    'id' => $post->user->id,
                    'name' => $post->user->name,
                    'username' => $post->user->username,
                    'avatar' => $post->user->media
                        ? url("storage/{$post->user->media->path}")
                        : null,
                    'is_following' => $isFollowing,
                    'is_private' => $post->user->is_private
                ],
            ],
        ], 201);
    }

    public function show($id, Request $request)
    {
        $authUser = Auth::user();

        $post = Post::with(['media', 'user.media'])->withCount(['likes', 'comments'])->findOrFail($id);

        if (!Gate::allows('view', $post)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $savedPost = $authUser->savedPost;
        $isSaved = $savedPost ? $savedPost->isSaved($post->id) : false;
        $isFollowing = $authUser->isFollowing($post->user);


        return response()->json(['post'=>[
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
                'is_private' => $post->user->is_private
            ],
        ]]);
    }

    public function update(Request $request, $id)
    {
        $post = Post::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'text'          => 'nullable|string',
            'privacy'       => 'nullable|in:public,private',
            'media'         => 'nullable|array',
            'media.*'       => 'file|mimes:jpeg,png,gif,mp4,mov|max:20480',
            'removedMedia'  => 'nullable|array',
            'removedMedia.*'=> 'exists:media,id',
        ]);

        $post->update([
            'text'    => $request->text ?? $post->text,
            'privacy' => $request->privacy ?? $post->privacy,
        ]);

        if ($request->filled('removedMedia')) {
            foreach ($post->media()->whereIn('id', $request->removedMedia)->get() as $media) {
                if (Storage::disk('public')->exists($media->path)) {
                    Storage::disk('public')->delete($media->path);
                }
                $media->delete();
            }
        }

        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $path = $file->store('posts', 'public');
                $type = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';

                $post->media()->create([
                    'path' => $path,
                    'type' => $type,
                ]);
            }
            $post->save();
        }
        app(EventPublisher::class)->publishEvent('PostUpdated',[
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
            'user' => [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'username' => $post->user->username,
                'avatar' => $post->user->media
                    ? url("storage/{$post->user->media->path}")
                    : null,
                'is_private' => $post->user->is_private
            ]
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
        app(EventPublisher::class)->publishEvent('PostDeleted',[ 'id'=> $id ]);

        $post->delete();



        return response()->json(['message' => 'Post deleted successfully']);
    }

    public function feed(Request $request)
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
        ]);

        $authUser = Auth::user();
        $page = $request->query('page', 1);
        $perPage = 10;

        $followingIds = $authUser->following()->pluck('id');

        $query = Post::whereIn('user_id', $followingIds)
                     ->with(['media', 'user.media'])
                     ->withCount(['likes', 'comments'])
                     ->latest();

        $posts = $query->paginate($perPage, ['*'], 'page', $page);

        $posts->getCollection()->transform(function ($post) use ($authUser) {
            $savedPost = $authUser->savedPost;
            $isSaved = $savedPost ? $savedPost->isSaved($post->id) : false;
            $isFollowing = $authUser->isFollowing($post->user);
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
                    'is_private' => $post->user->is_private
                ],
            ];
        });

        return response()->json([
            'posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'last_page' => $posts->lastPage(),
            ],
        ]);
    }

    public function groupPosts(Request $request)
    {
        $request->validate([
            'group_id' => 'required|exists:groups,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $authUser = Auth::user();
        $page = $request->query('page', 1);
        $perPage = 10;

        $query = Post::query()
                     ->where('group_id', $request->group_id)
                     ->with(['media', 'user.media'])
                     ->withCount(['likes', 'comments'])
                     ->latest();

        $query->whereDoesntHave('user.blockedUsers', function ($q) use ($authUser) {
            $q->where('blocked_id', $authUser->id);
        })->whereDoesntHave('user.blockedByUsers', function ($q) use ($authUser) {
            $q->where('blocker_id', $authUser->id);
        });

        $posts = $query->paginate($perPage, ['*'], 'page', $page);

        $posts->getCollection()->transform(function ($post) use ($authUser) {
            $savedPost = $authUser->savedPost;
            $isSaved = $savedPost ? $savedPost->isSaved($post->id) : false;
            $isFollowing = $authUser->isFollowing($post->user);
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
                    'is_private' => $post->user->is_private
                ],
            ];
        });

        return response()->json([
            'posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'last_page' => $posts->lastPage(),
            ],
        ]);
    }

}
