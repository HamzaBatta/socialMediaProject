<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Services\FirebaseService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\EventPublisher;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'ad_id' => 'nullable|integer',
            'comment_id' => 'nullable|exists:comments,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = 10;
        $page = $request->query('page', 1);
        $authUserId = Auth::id();

        $query = Comment::query()
                        ->with(['user.media'])
                        ->withCount(['likes', 'replies']);

        if ($request->filled('post_id')) {
            $query->where('commentable_type', 'Post')
                  ->where('commentable_id', $request->post_id);
        } elseif ($request->filled('ad_id')) {
            $query->where('commentable_type', 'Ad')
                  ->where('commentable_id', $request->ad_id);
        } elseif ($request->filled('comment_id')) {
            $query->where('commentable_type', 'Comment')
                  ->where('commentable_id', $request->comment_id);
        } else {
            return response()->json(['message' => 'post_id, ad_id or comment_id is required'], 422);
        }

        // Block logic
        $query->whereDoesntHave('user.blockedUsers', function ($q) use ($authUserId) {
            $q->where('blocked_id', $authUserId);
        })->whereDoesntHave('user.blockedByUsers', function ($q) use ($authUserId) {
            $q->where('blocker_id', $authUserId);
        });

        $comments = $query->paginate($perPage, ['*'], 'page', $page);

        $comments->getCollection()->transform(function ($comment) use($authUserId) {
            return [
                'id' => $comment->id,
                'text' => $comment->text,
                'likes_count' => $comment->likes_count,
                'replies_count' => $comment->replies_count,
                'is_liked' => $comment->isLikedBy($authUserId),
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'avatar' => $comment->user->media ? url("storage/{$comment->user->media->path}") : null,
                ],
                'created_at' => $comment->created_at,
            ];
        });

        return response()->json([
            'comments' => $comments->items(),
            'pagination' => [
                'current_page' => $comments->currentPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
                'last_page' => $comments->lastPage(),
            ],
        ]);
    }



    public function store(Request $request,FirebaseService $firebase)
    {
        $request->validate([
            'text' => 'required|string|max:1000',
            'commentable_type' => 'required|in:Post,Ad,Comment',
                'commentable_id' => 'required|integer',
        ]);
        $authUser = Auth::user();
        $comment = Comment::create([
            'text' => $request->text,
            'commentable_type' => $request->commentable_type,
            'commentable_id' => $request->commentable_id,
            'user_id' => $authUser->id,
        ]);


        if($request->commentable_type === 'Post') {
            $post = Post::with('user')->find($request->commentable_id);

            $post->increment('comments_count');
            $savedPost = $post->user->savedPost;
            $isSaved = $savedPost ? $savedPost->isSaved($post->id) : false;
            try{
                // Send notification to post owner
                if ($post->user && $post->user->device_token && $post->user !== $authUser) {
                    $firebase->sendStructuredNotification(
                        $post->user->device_token,
                        "{$authUser->name} commented on your post",
                        "$comment->text",
                        '/comments-page' ,
                        [
                            'post'=>[
                                'id' => $post->id,
                                'text' => $post->text,
                                'likes_count' => $post->likes_count,
                                'comments_count' => $post->comments_count,
                                'is_liked' => $post->isLikedBy($post->user->id),
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
                                    'is_following' => false,
                                    'is_private' => $post->user->is_private
                                ]
                            ]
                        ],
                        $authUser->media ? url("storage/{$authUser->media->path}") : null
                    );
                }
            }catch(Exception $e){
                Log::error('Failed to send notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            app(EventPublisher::class)->publishEvent('CommentCreated',[
            'id' => $comment->id,
            'text' => $request->text,
            'commentable_id' => $request->commentable_id,
            'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'avatar' => $comment->user->media ? url("storage/{$comment->user->media->path}") : null,
            ],
            'created_at' => $comment->created_at,

        ]);

        }elseif ($request->commentable_type === 'Comment') {
            try{
                $parentComment = Comment::with('user')->find($request->commentable_id);
                if ($parentComment && $parentComment->user && $parentComment->user->device_token &&$parentComment->user !== $authUser) {
                    $firebase->sendStructuredNotification(
                        $parentComment->user->device_token,
                        "{$authUser->name} replied to your comment",
                        $comment->text,
                        '/comments-page',
                        [
                            'post_id' =>$parentComment->getRootPostId()
                        ],
                        $authUser->media ? url("storage/{$authUser->media->path}") : null
                    );
                }
            }catch(Exception $e){
                Log::error('Failed to send notification', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $comment->loadCount('likes')->load('user.media');

        return response()->json([
            'message' => 'Comment created successfully',
            'comment' => [
                'id' => $comment->id,
                'text' => $comment->text,
                'likes_count' => $comment->likes_count,
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'avatar' => $comment->user->media ? url("storage/{$comment->user->media->path}") : null,
                ],
                'created_at' => $comment->created_at,
            ],
        ], 201);
    }


    public function show($id)
    {
        $authUserId = Auth::id();
        $comment = Comment::with(['user.media'])->withCount('likes','replies')->findOrFail($id);

        return response()->json([
            'comment' => [
                'id' => $comment->id,
                'text' => $comment->text,
                'likes_count' => $comment->likes_count,
                'is_liked' => $comment->isLikedBy($authUserId),
                'replies_count' => $comment->replies_count,
                'user' => [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'avatar' => $comment->user->media ? url("storage/{$comment->user->media->path}") : null,
                ],
                'created_at' => $comment->created_at,
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $comment->update(['text' => $request->text]);

        return response()->json(['message' => 'Comment updated successfully', 'comment' => $comment]);
    }

    public function destroy($id)
    {
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if($comment->commentable_type === 'Post'){
            Post::where('id',$comment->commentable_id)->decrement('comments_count');
            app(EventPublisher::class)->publishEvent('DeletedComment',[
                'id'=>$id
            ]);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
