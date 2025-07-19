<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\EventPublisher;
class BlockController extends Controller
{
    public function block(Request $request)
    {
        $request->validate([
            'targetId' => 'required|exists:users,id',
        ]);

        $currentUser = Auth::user();
        $targetUser = User::findOrFail($request->targetId);

        if ($currentUser->id === $targetUser->id) {
            return response()->json(['message' => 'You cannot block yourself.'], 400);
        }

        if ($currentUser->hasBlocked($targetUser)) {
            return response()->json(['message' => 'You have already blocked this user.'], 409);
        }

        $targetUser->following()->detach($currentUser->id);

        $currentUser->following()->detach($targetUser->id);

        $currentUser->blockedUsers()->attach($targetUser->id);

        app(EventPublisher::class)->publishEvent('BlockedUser',[
                'id' => $currentUser->id,
                'target' => $targetUser->email,
        ]);


        return response()->json(['message' => 'User blocked successfully.'], 200);
    }

    public function unblock(Request $request)
    {
        $request->validate([
            'targetId' => 'required|exists:users,id',
        ]);

        $currentUser = Auth::user();
        $targetUser = User::findOrFail($request->targetId);

        if (! $currentUser->hasBlocked($targetUser)) {
            return response()->json(['message' => 'You have not blocked this user.'], 400);
        }

        $currentUser->blockedUsers()->detach($targetUser->id);

        return response()->json(['message' => 'User unblocked successfully.'], 200);
    }

    public function blockedUsers()
    {
        $currentUser = Auth::user();
        $blocked = $currentUser->blockedUsers()->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
            ];
        });
        app(EventPublisher::class)->publishEvent('UnblockUser',[
                'id' => $currentUser->id,
                'target' => $targetUser->email,
        ]);


        return response()->json(['blocked_users' => $blocked]);
    }

}
