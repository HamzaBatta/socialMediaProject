<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Services\EventPublisher;
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
                'owner'=>[
                    'id'=>$owner->id,
                    'name'=>$owner->name,
                    'username'=>$owner->username,
                    'avatar' => $owner->media ? url("storage/{$owner->media->path}") : null,
                ],
                'avatar' => $group->media ? url("storage/{$group->media->path}") : null,
                'bio' => $group->bio,
            ]
        ]);
    }


    public function show(Request $request, $group_id)
    {
        $group = Group::with('media')->findOrFail($group_id);
        $user = $request->user();

        // Default: Not a member
        $isMember = false;

        // Check if user is authenticated
        if ($user) {
            // Owner is always a member
            if ($group->owner_id == $user->id) {
                $isMember = true;
            } else {
                // Check if user is an accepted member
                $isMember = $group->members()->where('user_id', $user->id)->exists();
            }
        }

        // If group is public or user is a member → load posts
        if ($group->privacy === 'public' || $isMember) {

            $posts = $group->posts()->with('media')->get()->map(function ($post) {
                return [
                    'id'         => $post->id,
                    'content'    => $post->content,
                    'privacy'    => $post->privacy,
                    'created_at' => $post->created_at,
                    'media'      => $post->media->map(fn($media) => [
                        'id'  => $media->id,
                        'type'=> $media->type,
                        'url' => url("storage/{$media->path}"),
                    ]),
                ];
            });


            return response()->json([
                'group' => [
                    'id'=>$group->id,
                    'name'=>$group->name,
                    'privacy'=>$group->privacy,
                    'owner_id'=>$group->owner_id,
                    'avatar' => $group->media ? url("storage/{$group->media->path}") : null,
                    'bio' => $group->bio
                ],
                'posts' => $posts,
            ]);
        }

        // Private group + not a member
        return response()->json([
            'group'  => [
                'id'=>$group->id,
                'name'=>$group->name,
                'privacy'=>$group->privacy,
                'owner_id'=>$group->owner_id,
                'avatar' => $group->media ? url("storage/{$group->media->path}") : null,
                'bio' => $group->bio
            ],
            'message' => 'This group is private.',
        ], 403);
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

            $path = $request->file('media')->store('group_media', 'public');
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

        if ($group->members()->where('user_id', $user->id)->exists()) {

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
        $group = Group::findOrFail($groupId);

        $members = $group->members()->with(['media'])->get()->map(function ($user) {
            return [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'avatar'    => $user->media ? url("storage/{$user->media->path}") : null,
                'role'     => $user->pivot->role,
            ];
        });

        return response()->json(['members' => $members], 200);
    }

    public function pendingRequests($groupId)
    {
        $group = Group::findOrFail($groupId);

        if ($group->owner_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requests = $group->requests()
                          ->where('state', 'pending')
                          ->with('creator.media')
                          ->get()
                          ->map(function ($request) {
                              return [
                                  'id'       => $request->id,
                                  'user_id'  => $request->creator->id,
                                  'name'     => $request->creator->name,
                                  'username' => $request->creator->username,
                                  'avatar'    => $request->creator->media ? url("storage/{$request->creator->media->path}") : null,
                                  'requested_at' => $request->requested_at,
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

        // Only the group owner can respond to requests (can extend this to admins later)
        if ($group->owner_id !== Auth::id()) {
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
        $groups = Group::where('owner_id', Auth::id())
                       ->with('media')
                       ->latest()
                       ->paginate(10);

        return response()->json([
            'groups' => $groups->through(function ($group) {
                return [
                    'id'      => $group->id,
                    'name'    => $group->name,
                    'privacy' => $group->privacy,
                    'bio'     => $group->bio,
                    'avatar'  => $group->media ? url("storage/{$group->media->path}") : null,
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
        $user = Auth::user();

        $groups = $user->groups()
                       ->with('media')
                       ->latest()
                       ->paginate(10);

        return response()->json([
            'groups' => $groups->through(function ($group) use ($user) {
                return [
                    'id'      => $group->id,
                    'name'    => $group->name,
                    'privacy' => $group->privacy,
                    'bio'     => $group->bio,
                    'avatar'  => $group->media ? url("storage/{$group->media->path}") : null,
                    'role'    => $group->pivot->role,
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
        $authUser = Auth::user();

        $groups = Group::whereDoesntHave('members', function ($query) use ($authUser) {
            $query->where('user_id', $authUser->id);
        })
                       ->where('owner_id', '!=', $authUser->id)
                       ->with('media')
                       ->latest()
                       ->paginate(10);

        return response()->json([
            'groups' => $groups->through(function ($group) {
                return [
                    'id'      => $group->id,
                    'name'    => $group->name,
                    'privacy' => $group->privacy,
                    'bio'     => $group->bio,
                    'avatar'  => $group->media ? url("storage/{$group->media->path}") : null,
                ];
            }),
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page'    => $groups->lastPage(),
                'per_page'     => $groups->perPage(),
                'total'        => $groups->total(),
            ]
        ]);
    }


}
