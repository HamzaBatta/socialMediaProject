<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'comment_id' => 'nullable|exists:comments,id',
            'page' => 'nullable|integer|min:1',
        ]);

        $perPage = 10;
        $page = $request->query('page', 1);

        $query = Comment::query()->with(['user.media'])->withCount('likes');

        if ($request->filled('post_id')) {
            $query->where('post_id', $request->post_id)
                  ->whereNull('reply_comment_id');
        } elseif ($request->filled('comment_id')) {
            $query->where('reply_comment_id', $request->comment_id);
        } else {
            return response()->json(['message' => 'post_id or comment_id is required'], 422);
        }

        $comments = $query->paginate($perPage, ['*'], 'page', $page);

        $comments->getCollection()->transform(function ($comment) {
            return [
                'id' => $comment->id,
                'text' => $comment->text,
                'likes_count' => $comment->likes_count,
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
            'post_id' => 'nullable|exists:posts,id',
            'reply_comment_id' => 'nullable|exists:comments,id',
        ]);

        if (!$request->post_id && !$request->reply_comment_id) {
            return response()->json(['message' => 'You must provide either post_id or reply_comment_id'], 422);
        }

        $comment = Comment::create([
            'text' => $request->text,
            'post_id' => $request->post_id,
            'reply_comment_id' => $request->reply_comment_id,
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
