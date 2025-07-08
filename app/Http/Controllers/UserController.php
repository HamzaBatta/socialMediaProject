<?php

namespace App\Http\Controllers;

use App\Mail\ChangeEmailEmails;
use App\Mail\VerifyEmails;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\Routing\Route;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\Request as FollowRequest;

class UserController extends Controller
{

    public function show($id)
    {
        $authUser = Auth::user();
        $user = User::with(['media','statuses'])->findOrFail($id);

        if ($authUser->isBlockedBy($user)) {
            return response()->json(['message' => 'You cannot view this profile.'], 403);
        }

        $isOwner = $authUser->id === $user->id;
        $isFollowing = $authUser->isFollowing($user);
        $hasBlocked = $authUser->hasBlocked($user);

        $isRequested = false;

        $isRequested = FollowRequest::where('user_id',$authUser->id)
                              ->where('requestable_type',User::class)
                              ->where('requestable_id',$user->id)
                              ->where('state','pending')
                              ->exists();

        $hasStatus = false;
        if ($user->statuses->isNotEmpty()) {
            if ($isOwner || $isFollowing) {
                $hasStatus = true;
            } elseif (!$user->is_private) {
                $hasStatus = $user->statuses->contains(fn($status) => $status->privacy === 'public');
            }
        }


        if (!$isOwner && $user->is_private && !$isFollowing) {
            return response()->json(['user'=>[
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
                'is_private' => $user->is_private,
                'posts_count' => $user->posts()->count(),
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'is_following' => $isFollowing,
                'is_blocked' => $hasBlocked,
                'is_requested' => $isRequested
            ]]);
        }

        return response()->json(['user'=>[
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
            'bio' => $user->bio,
            'is_private' => $user->is_private,
            'created_at' => $user->created_at,
            'personal_info' => $user->personal_info,
            'posts_count' => $user->posts()->count(),
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'is_following' => $isOwner ? 'owner' : $isFollowing,
            'has_status' => $hasStatus,
            'is_blocked' => $hasBlocked,
        ]]);
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
            'personal_info' => 'nullable|array',
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
            $user->is_private = $request->is_private;
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

        if($request->filled('personal_info')){
            $user->personal_info = $request->personal_info;
        }

        $user->save();

        $user->load('media');


        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Profile updated successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
                'is_private' => $user->is_private,
                'bio' => $user->bio,
                'personal_info' => $user->personal_info,
            ],
        ]);
    }

    public function changePassword(Request $request){
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if(!Hash::check($request->current_password,$user->password)){
            return response()->json(['message'=>'Current password is incorrect'],422);
        }
        $user->password= Hash::make($request->new_password);
        $user->save();
        return response()->json(['message'=>'Password changed successfully']);
    }

    public function requestChangeEmailCode()
    {
        $user = Auth::user();

        $code = mt_rand(1000, 9999);

        $user->verification_code = $code;
        $user->verification_code_sent_at = now();
        $user->save();

        Mail::to($user->email)->send(new ChangeemailEmails($code));

        return response()->json(['message' => 'Verification code sent to your current email.']);
    }
    public function verifyChangeEmailCode(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:4',
            'new_email' => 'required|email|unique:users,email',
        ]);

        $user = Auth::user();

        if ($user->verification_code != $request->code || now()->diffInMinutes($user->verification_code_sent_at) > 60) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        $user->email = $request->new_email;
        $user->email_verified_at = null;

        $code = mt_rand(1000, 9999);
        $user->verification_code = $code;
        $user->verification_code_sent_at = now();
        $user->save();

        Mail::to($request->new_email)->send(new VerifyEmails($code));


        return response()->json(['message' => 'Email changed successfully.']);
    }

    public function destroy()
    {
        $user = Auth::user();

        //delete user avatar
        if ($user->media) {
            Storage::disk('public')->delete($user->media->path);
            $user->media()->delete();
        }

        //delete user's posts
        foreach ($user->posts as $post) {
            foreach ($post->media as $media) {
                Storage::disk('public')->delete($media->path);
                $media->delete();
            }
            $post->delete();
        }

        //delete user's stories
        foreach ($user->stories as $story) {
            foreach ($story->media as $media) {
                Storage::disk('public')->delete($media->path);
                $media->delete();
            }
            $story->delete();
        }

        //handle groups the user owns
        $ownedGroups = Group::where('owner_id', $user->id)->get();

        foreach ($ownedGroups as $group) {
            $adminMember = $group->members()->where('role', 'admin')->first();

            if ($adminMember) {
                $group->update(['owner_id' => $adminMember->user_id]);
                $adminMember->update(['role' => 'owner']);
            } else {
                //delete group avatar
                if ($group->media) {
                    Storage::disk('public')->delete($group->media->path);
                    $group->media()->delete();
                }

                //delete group's posts
                foreach ($group->posts as $post) {
                    foreach ($post->media as $media) {
                        Storage::disk('public')->delete($media->path);
                        $media->delete();
                    }
                    $post->delete();
                }

                $group->delete();
            }
        }

        $user->groups()->detach();
        $user->delete();
        return response()->json(['message' => 'User and related data deleted successfully']);
    }

    public function followingWithStatus()
    {
        $user = Auth::user();
        $followingWithStatuses = $user->following()
                                      ->whereHas('statuses', function ($query) {
                                          $query->where('expiration_date', '>', now());
                                      })
                                      ->with('media')
                                      ->get()
                                      ->map(function ($following) {
                                          return [
                                              'id' => $following->id,
                                              'name' => $following->name,
                                              'username' => $following->username,
                                              'avatar' => $following->media
                                                  ? url("storage/{$following->media->path}")
                                                  : null,
                                          ];
                                      });
        return response()->json([
            'followings' => $followingWithStatuses,
        ]);
    }
}
