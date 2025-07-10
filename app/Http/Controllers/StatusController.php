<?php

namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Status;
use App\Http\Requests\StoreStatusRequest;
use App\Http\Requests\UpdateStatusRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $owner = User::findOrFail($request->user_id);
        $authUser = Auth::user();
        $isOwner = $authUser->id === $owner->id;
        $isFollower = $owner->followers()->where('follower_id', $authUser->id)->exists();

        if (!$isOwner) {
            if ($owner->is_private && !$isFollower) {
                return response()->json(['message' => 'You don\'t follow this user.'], 403);
            }
        }

        $statuses = Status::where('user_id', $owner->id)
                          ->where('expiration_date', '>', Carbon::now())
                          ->when(!$isOwner, function ($query) use ($owner, $isFollower) {
                              $query->where(function ($subQuery) use ($owner, $isFollower) {
                                  $subQuery->where('privacy', 'public');

                                  //allow private statuses if the viewer is a follower
                                  if ($isFollower) {
                                      $subQuery->orWhere('privacy', 'private');
                                  }
                              });
                          })
                          ->with(['media', 'user'])->withCount('likes')
                          ->latest()
                          ->get();

        return response()->json([
            'statuses' => $statuses->map(fn($status) => [
                'id' => $status->id,
                'text' => $status->text,
                'expiration_date' => $status->expiration_date,
                'privacy' => $status->privacy,
                'likes_count' => $status->likes_count,
                'is_liked' => $status->isLikedBy($authUser->id),
                'media' => $status->media ? [
                    'id' => $status->media->id,
                    'type' => $status->media->type,
                    'url' => url("storage/{$status->media->path}"),
                ] : null,
                'created_at' => $status->created_at,
                'user' => [
                    'id' => $status->user->id,
                    'name' => $status->user->name,
                    'username' => $status->user->username,
                    'avatar' => $status->user->media ? url("storage/{$status->user->media->path}") : null,
                ],
            ]),
        ]);
    }

    public function store(Request $request)
    {

        $request->validate([
            'text' => 'nullable|string',
            'media' => 'nullable|file',
            'privacy'   => ['required', 'in:public,private'],
        ]);
        $status = Status::create([
            'user_id' => Auth::id(),
            'text' => $request->text,
            'expiration_date' =>Carbon::now()->addDay(),
            'privacy' => $request->privacy
        ]);

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $path = $file->store('statuses', 'public');
            $type = str_starts_with($file->getMimeType(), 'video') ? 'video' : 'image';
            $status->media()->create([
                'path' => $path,
                'type' => $type,
            ]);
        }

        return response()->json(['message' => 'Status created successfully', 'status' => $status], 201);
    }

    public function show(Request $request, $id)
    {
        $authUser = Auth::user();
        $status = Status::with('media', 'user')
                        ->withCount('likes')
                        ->findOrFail($id);

        $this->authorize('view', $status);

        return response()->json(['status'=>[
            'id' => $status->id,
            'text' => $status->text,
            'expiration_date' => $status->expiration_date,
            'privacy' => $status->privacy,
            'likes_count' => $status->likes_count,
            'is_liked' => $status->isLikedBy($authUser->id),
            'media' => $status->media ? [
                'id' => $status->media->id,
                'type' => $status->media->type,
                'url' => url("storage/{$status->media->path}"),
            ] : null,
            'created_at' => $status->created_at,
            'user' => [
                'id' => $status->user->id,
                'name' => $status->user->name,
                'username' =>$status->user->username,
                'avatar' => $status->user->media ? url("storage/{$status->user->media->path}") : null,
            ],
        ]]);
    }

    public function destroy($id)
    {
        $status = Status::where('user_id', Auth::id())->findOrFail($id); // Changed to use Auth facade

        if ($status->media && Storage::disk('public')->exists($status->media->path)) {
            Storage::disk('public')->delete($status->media->path);
            $status->media->delete();
        }

        $status->delete();

        return response()->json(['message' => 'Status deleted successfully']);
    }
    public function addToHighlight(Request $request)
    {
        $request->validate([
            'highlight_id' => 'required|exists:highlights,id',
            'status_id' => 'required|exists:statuses,id',
        ]);

        $authUser = Auth::user();

        $highlight = Highlight::where('id', $request->highlight_id)
                              ->where('user_id', $authUser->id)
                              ->firstOrFail();

        $status = Status::where('id', $request->status_id)
                        ->where('user_id', $authUser->id)
                        ->firstOrFail();

        if ($highlight->statuses()->where('statuses.id', $status->id)->exists()) {
            return response()->json(['message' => 'Status already in highlight.'], 200);
        }

        $highlight->statuses()->attach($status->id, [
            'added_at' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Status added to highlight.']);
    }
    public function archivedStatuses(Request $request){
        $authUser = Auth::user();
        $statuses = Status::where('user_id',$authUser->id)
                                 ->where('expiration_date','<',Carbon::now())->get();
        return  response()->json([
            'statuses'=> $statuses
        ]);
    }
}
