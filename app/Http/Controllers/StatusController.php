<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Http\Requests\StoreStatusRequest;
use App\Http\Requests\UpdateStatusRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\User; // Added import for User model
use Illuminate\Support\Facades\Auth; // Added import for Auth facade

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $owner = User::findOrFail($request->user_id);
        $authUser = Auth::user();

        if ($authUser->id !== $owner->id) {
            if ($owner->is_private && !$owner->followers()->where('follower_id', Auth::id())->exists()) {
                return response()->json(['message' => 'you don\'t follow this user']);
            }
        }

        $statuses = Status::where('user_id', $owner->id)
                          ->where('expiration_date', '>', now())
                          ->with(['media', 'user'])
                          ->latest()
                          ->get();

        return response()->json([
            'statuses' => $statuses->map(fn($status) => [
                'id' => $status->id,
                'text' => $status->text,
                'expiration_date' => $status->expiration_date,
                'media' => $status->media ? [
                    'id' => $status->media->id,
                    'type' => $status->media->type,
                    'url' => url("storage/{$status->media->path}"),
                ] : null,
                'created_at' => $status->created_at,
                'user' => [
                    'id' => $status->user->id,
                    'name' => $status->user->name,
                    'username' =>$status->user->username
                ],
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string',
            'media' => 'nullable|file',
        ]);
        $status = Status::create([
            'user_id' => Auth::id(), // Changed to use Auth facade
            'text' => $request->text,
            'expiration_date' => now()->addDay(),
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

        return response()->json(['message' => 'Status created successfully', 'status_id' => $status->id], 201);
    }

    public function show(Request $request, $id)
    {
        $status = Status::with('media', 'user')->findOrFail($id);

        $this->authorize('view', $status);

        return response()->json([
            'id' => $status->id,
            'text' => $status->text,
            'expiration_date' => $status->expiration_date,
            'media' => $status->media ? [
                'id' => $status->media->id,
                'type' => $status->media->type,
                'url' => url("storage/{$status->media->path}"),
            ] : null,
            'created_at' => $status->created_at,
            'user' => [
                'id' => $status->user->id,
                'name' => $status->user->name,
                'username' =>$status->user->username
            ],
        ]);
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
}
