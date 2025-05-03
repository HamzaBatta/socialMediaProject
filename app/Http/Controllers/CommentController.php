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
        ]);

        if ($request->filled('post_id')) {
            $comments = Comment::where('post_id', $request->post_id)
                               ->whereNull('reply_comment_id')
                               ->withCount('likes')
                               ->with(['user'])
                               ->get();
        } elseif ($request->filled('comment_id')) {
            $comments = Comment::where('reply_comment_id', $request->comment_id)
                               ->withCount('likes')
                               ->with(['user'])
                               ->get();
        } else {
            return response()->json(['message' => 'post_id or comment_id is required'], 422);
        }

        return response()->json(['comments' => $comments]);
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

        return response()->json([
            'message' => 'Comment created successfully',
            'comment' => $comment->loadCount('likes')->load('user'),
        ], 201);
    }

    public function show($id)
    {
        $comment = Comment::withCount('likes')->with(['user'])->findOrFail($id);

        return response()->json(['comment' => $comment]);
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
