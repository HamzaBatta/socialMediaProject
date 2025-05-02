<?php

namespace App\Http\Controllers;

use App\Models\Like;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LikeController extends Controller
{
    public function toggle(Request $request)
    {
        $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'comment_id' => 'nullable|exists:comments,id',
        ]);

        if (!$request->post_id && !$request->comment_id) {
            return response()->json(['message' => 'post_id or comment_id is required'], 422);
        }

        if ($request->post_id && $request->comment_id) {
            return response()->json(['message' => 'Only one of post_id or comment_id should be provided'], 422);
        }

        $user = Auth::user();

        $like = Like::where('user_id', $user->id)
                    ->where('post_id', $request->post_id)
                    ->where('comment_id', $request->comment_id)
                    ->first();

        if ($like) {
            $like->delete();
            return response()->json(['message' => 'Unliked successfully'], 200);
        } else {
            Like::create([
                'user_id' => $user->id,
                'post_id' => $request->post_id,
                'comment_id' => $request->comment_id,
            ]);
            return response()->json(['message' => 'Liked successfully'], 201);
        }
    }
}
