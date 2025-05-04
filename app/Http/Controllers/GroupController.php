<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User; 

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'privacy' => 'required|in:public,private',
            'media'   => 'nullable|image|max:2048',
        ]);

        $validated['creator_id'] = Auth::id();
        $group = Group::create($validated);

        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('group_media', 'public');
            $group->media()->create(['path' => $path]);
        }

        return response()->json(['data' => $group->load('media')], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $groupId = $request->group_id;
        $group = Group::with('media')->findOrFail($groupId);

        return response()->json(['data' => $group], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Group $group)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $groupId =$request->group_id;
        $group = Group::findOrFail($groupId);

        $validated = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'privacy' => 'sometimes|required|in:public,private',
            'media'   => 'nullable|image|max:2048',
        ]);

        $group->update($validated);

        if ($request->hasFile('media')) {
            if ($group->media) {
                Storage::disk('public')->delete($group->media->path);
                $group->media()->delete();
            }
            $path = $request->file('media')->store('group_media', 'public');
            $group->media()->create(['path' => $path]);
        }

        return response()->json(['data' => $group->load('media')], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $groupId = $request->group_id;
        $group = Group::findOrFail($groupId);

        if ($group->media) {
            Storage::disk('public')->delete($group->media->path);
        }
        if ($group->creator_id == Auth::id()) {
            $group->delete();
        }else{
            
            return response()->json(['message' => 'you are not authorized'], 403);
        }

        return response()->json(null, 204);
    }

    public function join(Request $request)
    {
        $groupId = $request->group_id;
        $group = Group::findOrFail($groupId);
        $userId = Auth::id();
        $user = User::find($userId);

        if (! $group->members()->where('user_id', $user->id)->exists()) {
            if($group->privacy === 'private'){
                $existingRequest = $group->requests()
                                            ->where('user_id', $user->id)
                                            ->where('state', 'pending')
                                            ->first();
                                                    
                if (!$existingRequest) {
                    $sentRequest = $group->requests()->create([
                        'user_id' => $user->id,
                        'requested_at' => now(),
                    ]);
                    return response()->json(['message' => 'sent a follow request'], 200);
                }else {
                    $existingRequest->delete();
                    return response()->json(['message' => 'unsent the follow request'], 200);
                }
                 
            }

            $group->members()->attach($user->id);
            return response()->json(['message' => 'You have joined the group.'], 200);
        }

        return response()->json(['message' => 'Already a member.'], 200);
    }

    public function leave(Request $request)
    {
        $groupId = $request->group_id;
        $group = Group::findOrFail($groupId);
        $userId = Auth::id();
        $user = User::find($userId);

        if ($group->members()->where('user_id', $user->id)->exists()) {
            $group->members()->detach($user->id);
            return response()->json(['message' => 'You have left the group.'], 200);
        }

        return response()->json(['message' => 'Not a member.'], 200);
    }
}
