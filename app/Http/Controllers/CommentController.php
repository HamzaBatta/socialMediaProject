<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $authUser = Auth::user();

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
        $query->whereDoesntHave('user.blockedUsers', function ($q) use ($authUser) {
            $q->where('blocked_id', $authUser->id);
        })->whereDoesntHave('user.blockedByUsers', function ($q) use ($authUser) {
            $q->where('blocker_id', $authUser->id);
        });

        $comments = $query->paginate($perPage, ['*'], 'page', $page);

        $comments->getCollection()->transform(function ($comment) {
            return [
                'id' => $comment->id,
                'text' => $comment->text,
                'likes_count' => $comment->likes_count,
                'replies_count' => $comment->replies_count,
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



    public function store(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:1000',
            'commentable_type' => 'required|in:Post,Ad,Comment',
                'commentable_id' => 'required|integer',
        ]);

        $comment = Comment::create([
            'text' => $request->text,
            'commentable_type' => $request->commentable_type,
            'commentable_id' => $request->commentable_id,
            'user_id' => auth()->id(),
        ]);

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
        $comment = Comment::with(['user.media'])->withCount('likes')->findOrFail($id);

        return response()->json([
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

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }
}
