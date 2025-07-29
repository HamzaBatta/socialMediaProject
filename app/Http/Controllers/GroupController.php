<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Services\EventPublisher;
use App\Models\Request as FollowRequest;
class GroupController extends Controller
{


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'privacy' => 'required|in:public,private',
            'avatar'  => 'nullable|image|max:2048',
            'bio'     => 'nullable|string|max:255',
        ]);

        $validated['owner_id'] = Auth::id();
        $group = Group::create($validated);

        // Add owner as a member with 'owner' role
        $group->members()->attach(Auth::id(), ['role' => 'owner']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('group_avatars', 'public');
            $group->media()->create(['path' => $path]);
        }

        $owner = User::findOrFail($group->owner_id);


        app(EventPublisher::class)->publishEvent('GroupCreated',$validated);



        return response()->json([
            'message' =>'Group created successfully',
            'group' =>[
                'id'=>$group->id,
                'name'=>$group->name,
                'privacy'=>$group->privacy,
                'bio' => $group->bio,
                'owner'=>[
                    'id'=>$owner->id,
                    'name'=>$owner->name,
                    'username'=>$owner->username,
                    'avatar' => $owner->media ? url("storage/{$owner->media->path}") : null,
                ],
                'avatar' => $group->media ? url("storage/{$group->media->path}") : null,
            ]
        ]);
    }


    public function show(Request $request, $group_id)
    {
        $group = Group::with('media')
                      ->withCount('members')
                      ->findOrFail($group_id);
        $user = $request->user();

        $isMember = $group->isMember($user->id);
        $role = null;
        if ($isMember) {
            $role = $group->members()->where('user_id', $user->id)->value('role');
        }


        $owner = User::where('id',$group->owner_id)->with('media')->firstOrFail();

        $isRequested = FollowRequest::isRequested($group->requests(),$user->id,Group::class,$group->id);

        $joinStatus = $group->joinStatus($isMember,$isRequested);



        // If group is public or user is a member
        if ($group->privacy === 'public' || $isMember) {

            return response()->json([
                'group' => [
                    'id'=>$group->id,
                    'name'=>$group->name,
                    'privacy'=>$group->privacy,
                    'avatar' => $group->media ? url("storage/{$group->media->path}") : null,
                    'bio' => $group->bio,
                    'members_count' => $group->members_count,
                    'join_status' => $joinStatus,
                    'role' => $role,
                    'owner' => [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'username' => $owner->username,
                        'avatar' => $owner->media ? url("storage/{$owner->media->path}") : null,
                    ]
                ],
            ]);
        }

        // Private group + not a member
        return response()->json([
            'group'  => [
                'id'=>$group->id,
                'name'=>$group->name,
                'privacy'=>$group->privacy,
                'members_count' => $group->members_count,
                'join_status' => $joinStatus,
                'avatar' => $group->media ? url("storage/{$group->media->path}") : null,
                'owner' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'username' => $owner->username,
                    'avatar' => $owner->media ? url("storage/{$owner->media->path}") : null,
                ]

            ],
            'message' => 'This group is private.',
        ], 200);
    }


    public function update(Request $request, $group_id)
    {
        $group = Group::findOrFail($group_id);

        if ($group->owner_id !== Auth::id()) {
            return response()->json(['message' => 'You are not authorized to update this group.'], 403);
        }

        $validated = $request->validate([
            'name'    => 'sometimes|required|string|max:255',
            'privacy' => 'sometimes|required|in:public,private',
            'avatar'   => 'nullable|image|max:2048',
            'bio' => 'sometimes|required|string|max:255'
        ]);

        $group->update($validated);

        if ($request->hasFile('avatar')) {
            if ($group->media) {
                Storage::disk('public')->delete($group->media->path);
                $group->media()->delete();
            }
            $path = $request->file('avatar')->store('group_avatars', 'public');
            $group->media()->create(['path' => $path]);
        }

        app(EventPublisher::class)->publishEvent('GroupUpdated',$validated);

        return response()->json(['group' => [
            'id'=>$group->id,
            'name'=>$group->name,
            'privacy'=>$group->privacy,
            'owner_id'=>$group->owner_id,
            'avatar' => $group->media ? url("storage/{$group->media->path}") : null,
            'bio' => $group->bio
        ]]);
    }
    public function destroy(Request $request, $group_id)
    {
        $group = Group::findOrFail($group_id);

        if ($group->owner_id !== Auth::id()) {
            return response()->json(['message' => 'You are not authorized to delete this group.'], 403);
        }

        if ($group->media) {
            Storage::disk('public')->delete($group->media->path);
            $group->media()->delete();
        }

        //delete group's posts and their media
        foreach ($group->posts as $post) {
            foreach ($post->media as $media) {
                Storage::disk('public')->delete($media->path);
                $media->delete();
            }
            $post->delete();
        }

        $group->delete();

        app(EventPublisher::class)->publishEvent('GroupDeleted',[
            'id'=> $group->id
        ]);
        return response()->json(['message' => 'Group deleted successfully.'], 204);
    }

    public function join(Request $request,$group_id)
    {
        $groupId = $request->group_id;
        $group = Group::findOrFail($groupId);
        $userId = Auth::id();
        $user = User::find($userId);

        if (! $group->isMember($user->id)) {
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

            app(EventPublisher::class)->publishEvent('JoinedGroup',[
                'id'=> $group->id,
                'user'=>$user->id
            ]);

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

        if ($group->isMember($user->id)) {

            $group->members()->detach($user->id);

            app(EventPublisher::class)->publishEvent('LeaveGroup',[
                'id'=> $group->id,
                'user'=>$user->id
            ]);


            return response()->json(['message' => 'You have left the group.'], 200);
        }

        return response()->json(['message' => 'Not a member.'], 200);
    }

    public function members($groupId)
    {
        $authUser = Auth::user();

        $group = Group::findOrFail($groupId);

        $members = $group->members()->with(['media'])->get()->map(function ($user) use ($authUser) {
            $isOwner = $authUser->id === $user->id;
            $isFollowing = $authUser->isFollowing($user);
            $isRequested = FollowRequest::isRequested($authUser->requests(),$authUser->id,User::class,$user->id);
            return [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'avatar'    => $user->media ? url("storage/{$user->media->path}") : null,
                'role'     => $user->pivot->role,
                'is_private' => $user->is_private,
                'is_following' => $isOwner ? 'owner' : $isFollowing,
                'is_requested' => $isRequested,
            ];
        });

        return response()->json(['members' => $members], 200);
    }

    public function pendingRequests($groupId)
    {
        $group = Group::findOrFail($groupId);

        $authUserId = Auth::id();

        $isOwner = $group->owner_id === $authUserId;

        $isAdmin = $group->members()
                         ->where('user_id', $authUserId)
                         ->wherePivot('role', 'admin')
                         ->exists();

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requests = $group->requests()
                          ->where('state', 'pending')
                          ->with('creator.media')
                          ->get()
                          ->map(function ($request) {
                              return [
                                  'id'       => $request->id,
                                  'requested_at' => $request->requested_at,
                                  'creator' => [
                                      'user_id'  => $request->creator->id,
                                      'name'     => $request->creator->name,
                                      'username' => $request->creator->username,
                                      'avatar'    => $request->creator->media ? url("storage/{$request->creator->media->path}") : null,
                                  ],
                              ];
                          });

        return response()->json(['requests' => $requests]);
    }

    public function respondToRequest(Request $request, $groupId)
    {
        $validated = $request->validate([
            'request_id' => 'required|exists:requests,id',
            'state'      => 'required|in:approved,rejected',
        ]);

        $group = Group::findOrFail($groupId);

        $authUserId = Auth::id();

        $isOwner = $group->owner_id === $authUserId;

        $isAdmin = $group->members()
                         ->where('user_id', $authUserId)
                         ->wherePivot('role', 'admin')
                         ->exists();

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $joinRequest = $group->requests()->where('id', $validated['request_id'])->firstOrFail();

        $joinRequest->update(['state' => $validated['state']]);

        if ($validated['state'] === 'approved') {
            $group->members()->attach($joinRequest->user_id, ['role' => 'member']);

            app(EventPublisher::class)->publishEvent('JoinedGroup',[
                'id'=> $group->id,
                'user'=>$joinRequest->user_id
            ]);

            return response()->json(['message' => 'Request approved and user added to group.']);
        }

        return response()->json(['message' => 'Request rejected.']);
    }

    public function myOwnedGroups(Request $request)
    {
        $authUser = Auth::user();
        $groups = $authUser->groups()->where('owner_id', $authUser->id)
                       ->with('media')
                       ->withCount('members')
                       ->latest()
                       ->paginate(10);

        return response()->json([
            'groups' => $groups->through(function ($group) use($authUser) {
                $isMember = $group->isMember($authUser->id);
                $isRequested = FollowRequest::isRequested($group->requests(),$authUser->id,Group::class,$group->id);
                $joinStatus = $group->joinStatus($isMember,$isRequested);
                return [
                    'id'      => $group->id,
                    'name'    => $group->name,
                    'privacy' => $group->privacy,
                    'bio'     => $group->bio,
                    'join_status' => $joinStatus,
                    'members_count' => $group->members_count,
                    'role' => "owner",
                    'avatar'  => $group->media ? url("storage/{$group->media->path}") : null,
                    'owner_id' => $group->owner_id,
                ];
            }),
            'pagination' => [
                'current_page' => $groups->currentPage(),
                'last_page'    => $groups->lastPage(),
                'per_page'     => $groups->perPage(),
                'total'        => $groups->total(),
            ]
        ]);
    }
    public function myGroups(Request $request)
    {
        $authUser = Auth::user();

        $groups = $authUser->groups()
                       ->whereNot('owner_id',$authUser->id)
                       ->with('media')
                       ->latest()
                       ->paginate(10);

        return response()->json([
            'groups' => $groups->through(function ($group) use($authUser) {
                $isMember = $group->isMember($authUser->id);
                $isRequested = FollowRequest::isRequested($group->requests(),$authUser->id,Group::class,$group->id);
                $joinStatus = $group->joinStatus($isMember,$isRequested);
                return [
                    'id'      => $group->id,
                    'name'    => $group->name,
                    'privacy' => $group->privacy,
                    'bio'     => $group->bio,
                    'join_status' => $joinStatus,
                    'avatar'  => $group->media ? url("storage/{$group->media->path}") : null,
                    'role'    => $group->pivot->role,
                    'owner_id' => $group->owner_id,
                ];
            }),
            'pagination' => [
                'current_page' => $groups->currentPage(),
                'last_page'    => $groups->lastPage(),
                'per_page'     => $groups->perPage(),
                'total'        => $groups->total(),
            ]
        ]);
    }

        public function exploreGroups(Request $request)
    {
        $user_id = Auth::id();

        $groups = Group::whereDoesntHave('members', function ($query) use ($user_id) {
            $query->where('user_id',$user_id);
        })
                       ->where('owner_id', '!=', $user_id)
                       ->with('media')
                       ->latest()
                       ->paginate(10);



        return response()->json([
            'groups' => $groups->through(function ($group) use($user_id){

                $isMember = $group->isMember($user_id);

                $isRequested = FollowRequest::isRequested($group->requests(),$user_id,Group::class,$group->id);

                $joinStatus = $group->joinStatus($isMember,$isRequested);

                return [
                    'id'      => $group->id,
                    'name'    => $group->name,
                    'privacy' => $group->privacy,
                    'bio'     => $group->bio,
                    'join_status' => $joinStatus,
                    'avatar'  => $group->media ? url("storage/{$group->media->path}") : null,
                    'owner_id' => $group->owner_id,
                ];
            }),
            'pagination' => [
                'current_page' => $groups->currentPage(),
                'last_page'    => $groups->lastPage(),
                'per_page'     => $groups->perPage(),
                'total'        => $groups->total(),
            ]
        ]);
    }

    public function pendingAllRequests()
    {
        $groups = Auth::user()->groups()->wherePivotIn('role', ['owner', 'admin'])->get();
        $groupIds = $groups->pluck('id');

        $requests = FollowRequest::where('requestable_type', Group::class)
                           ->whereIn('requestable_id', $groupIds)
                           ->where('state', 'pending')
                           ->with('creator.media')
                           ->get()
                           ->map(function ($request) {
                               $group = Group::where('id',$request->requestable_id)->with('media')->firstOrFail();
                               return [
                                   'id'       => $request->id,
                                   'requested_at' => $request->requested_at,
                                   'creator'=>[
                                       'user_id'  => $request->creator->id,
                                       'name'     => $request->creator->name,
                                       'username' => $request->creator->username,
                                       'avatar'   => $request->creator->media ? url("storage/{$request->creator->media->path}") : null,
                                   ],
                                   'group' => [
                                       'id'      => $group->id,
                                       'name'    => $group->name,
                                       'privacy' => $group->privacy,
                                       'bio'     => $group->bio,
                                       'avatar'  => $group->media ? url("storage/{$group->media->path}") : null,
                                   ],
                               ];
                           });

        return response()->json(['requests' => $requests]);
    }


    public function changeRole(Request $request, $groupId)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role'    => 'required|in:admin,member',
        ]);

        $user_id = $request->user_id;

        $group = Group::findOrFail($groupId);

        if ($group->owner_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user_id == $group->owner_id) {
            return response()->json(['message' => 'Cannot change the role of the owner'], 422);
        }

        $isMember = $group->isMember($user_id);

        if (!$isMember) {
            return response()->json(['message' => 'User is not a member of the group'], 404);
        }

        $group->members()->updateExistingPivot($user_id, [
            'role' => $request->role
        ]);

        return response()->json(['message' => 'Member role updated successfully']);
    }


}
