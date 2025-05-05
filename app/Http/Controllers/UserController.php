<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{

    public function show($id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'bio' => $user->bio,
            'is_private' => $user->is_private,
            'created_at' => $user->created_at,
        ]);
    }

    public function updateUsername(Request $request)
    {
        $request->validate([
            'username' => [
                'required',
                'string',
                'unique:users,username',
                'min:3',
                'max:20',
                'regex:/^[a-zA-Z0-9_]+$/', //only letters,numbers,underscores
            ],
        ]);
        $user = Auth::user();
        $user->username = $request->username;
        $user->save();

        return response()->json(['message' => 'Username updated successfully']);
    }
    public function updateBio(Request $request)
    {
        $request->validate([
            'bio' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $user->bio = $request->bio;
        $user->save();

        return response()->json(['message' => 'Bio updated successfully']);
    }

    public function togglePrivacy(Request $request)
    {
        $user = Auth::user();
        $user->is_private = !$user->is_private;
        $user->save();

        return response()->json([
            'message' => 'Privacy setting updated',
            'is_private' => $user->is_private,
        ]);
    }
}
