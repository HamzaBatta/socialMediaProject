<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Routing\Route;

class UserController extends Controller
{

    public function show($id)
    {
        $user = User::findOrFail($id);
        if($user->is_private){
            $requester = User::findOrFail(Auth::id());
            if(!$requester->isFollowing($user)){
                return response()->json(['message' => 'this user is private'], 403);
            }
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'bio' => $user->bio,
            'is_private' => $user->is_private,
            'created_at' => $user->created_at,
            'personal_info'=> $user->personal_info,
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

        $user = User::findOrFail(Auth::id());
        $user->username = $request->username;
        $user->save();

        return response()->json(['message' => 'Username updated successfully']);
    }
    public function updateBio(Request $request)
    {
        $request->validate([
            'bio' => 'nullable|string|max:255',
        ]);
$user = User::findOrFail(Auth::id());
        $user->bio = $request->bio;
        $user->save();

        return response()->json(['message' => 'Bio updated successfully']);
    }

    public function togglePrivacy(Request $request)
    {
        $user = User::findOrFail(Auth::id());
        $user->is_private = !$user->is_private;
        $user->save();

        return response()->json([
            'message' => 'Privacy setting updated',
            'is_private' => $user->is_private,
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