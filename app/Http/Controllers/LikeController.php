<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    public function toggle(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'comment_id' => 'nullable|exists:comments,id',
            'status_id' => 'nullable|exists:statuses,id',
        ]);

        $provided = array_filter([
            'post_id' => $request->post_id,
            'comment_id' => $request->comment_id,
            'status_id' => $request->status_id,
        ]);

        if (count($provided) !== 1) {
            return response()->json(['message' => 'Exactly one of post_id, comment_id, or status_id must be provided'], 422);
        }

        $user = auth()->user();

        // Determine the type and ID
        if ($request->post_id) {
            $likeableType = Post::class;
            $likeableId = $request->post_id;
        } elseif ($request->comment_id) {
            $likeableType = Comment::class;
            $likeableId = $request->comment_id;
        } else {
            $likeableType = Status::class;
            $likeableId = $request->status_id;
        }

        $like = Like::where('user_id', $user->id)
                    ->where('likeable_type', $likeableType)
                    ->where('likeable_id', $likeableId)
                    ->first();

        if ($like) {
            $like->delete();
            return response()->json(['message' => 'Unliked successfully'], 200);
        } else {
            Like::create([
                'user_id' => $user->id,
                'likeable_type' => $likeableType,
                'likeable_id' => $likeableId,
            ]);
            return response()->json(['message' => 'Liked successfully'], 201);
        }
    }
}
