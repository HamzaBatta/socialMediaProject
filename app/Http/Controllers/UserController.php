<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\Routing\Route;

class UserController extends Controller
{

    public function show($id)
    {
        $authUser = Auth::user();

        $user = User::findOrFail($id);
        if($authUser->id!==$user->id){
            if($user->is_private){
                $requester = User::findOrFail(Auth::id());
                if(!$requester->isFollowing($user)){
                    return response()->json(['message' => 'this user is private'], 403);
                }
            }
        }
        $user = User::findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'Email' => $user->email,
            'username' => $user->username,
            'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
            'bio' => $user->bio,
            'is_private' => $user->is_private,
            'created_at' => $user->created_at,
            'personal_info'=> $user->personal_info,
        ]);
    }

    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],
        ]);

        $username = $request->username;

        $exists = User::where('username', $username)
                      ->where('id', '!=', Auth::id())
                      ->exists();

        if ($exists) {
            return response()->json(['valid' => false, 'message' => 'Username is already taken']);
        }

        return response()->json(['valid' => true, 'message' => 'Username is available']);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:20',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users')->ignore($user->id),
            ],
            'name' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:255',
            'is_private' => 'nullable|boolean',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->filled('username')) {
            $user->username = $request->username;
        }

        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        if ($request->filled('bio')) {
            $user->bio = $request->bio;
        }

        if ($request->has('is_private')) {
            $user->is_private = $request->boolean('is_private');
        }

        if ($request->hasFile('avatar')) {
            if ($user->media) {
                Storage::disk('public')->delete($user->media->path);
                $user->media()->delete();
            }

            $path = $request->file('avatar')->store('avatars', 'public');

            $user->media()->create([
                'path' => $path,
                'type' => 'image',
            ]);
        }

        $user->save();

        $user->load('media');

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
                'is_private' => $user->is_private,
                'bio' => $user->bio,
            ],
        ]);
    }

    public function updatePersonalInfo(Request $request){
        $user = User::findOrFail(Auth::id());
        $request->validate([
            'personal_info' => 'required|array',
        ]);
        $user->personal_info = $request->input('personal_info');
        $user->save();
        return response()->json(['message' => 'personal info updated successfully.']);
    }
}
