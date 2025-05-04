<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Http\Requests\StoreStatusRequest;
use App\Http\Requests\UpdateStatusRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $statuses = Status::where('user_id', $request->user_id)
                          ->where('expiration_date', '>', now())
                          ->with('media')
                          ->latest()
                          ->get();

        return response()->json([
            'statuses' => $statuses->map(function ($status) {
                return [
                    'id' => $status->id,
                    'text' => $status->text,
                    'expiration_date' => $status->expiration_date,
                    'media' => $status->media ? [
                        'id' => $status->media->id,
                        'type' => $status->media->type,
                        'url' => url("storage/{$status->media->path}"),
                    ] : null,
                    'created_at' => $status->created_at,
                ];
            }),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string',
            'media' => 'nullable|file',
        ]);
        $status = Status::create([
            'user_id' => auth()->id(),
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

    public function show($id)
    {
        $status = Status::with('media')->findOrFail($id);

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
        ]);
    }

    public function destroy($id)
    {
        $status = Status::where('user_id', auth()->id())->findOrFail($id);

        if ($status->media && Storage::disk('public')->exists($status->media->path)) {
            Storage::disk('public')->delete($status->media->path);
            $status->media->delete();
        }

        $status->delete();

        return response()->json(['message' => 'Status deleted successfully']);
    }
}
