<?php
namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Status;
use App\Models\StatusHighlights;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HighlightController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $highlights = $user->highlights()->with(['media', 'statuses'])->get()->map(function ($highlight) {
            return [
                'id' => $highlight->id,
                'text' => $highlight->text,
                'cover' => $highlight->media ? url("storage/{$highlight->media->path}") : null,
                'statuses_count' => $highlight->statuses->count(),
            ];
        });

        return response()->json(['highlights' => $highlights]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string|max:255',
            'status_ids' => 'required|array|min:1',
            'status_ids.*' => 'exists:statuses,id',
            'cover' => 'nullable|image|max:5120',
        ]);

        $user = Auth::user();

        DB::beginTransaction();

        try {
            $highlight = Highlight::create([
                'text' => $request->text,
                'user_id' => $user->id,
            ]);
            // Attach statuses with added_at
            foreach ($request->status_ids as $statusId) {
                $highlight->statuses()->attach($statusId, ['added_at' => Carbon::now()]);
            }

            // Custom cover provided
            if ($request->hasFile('cover')) {
                $path = $request->file('cover')->store('media', 'public');

                $highlight->media()->create([
                    'path' => $path,
                    'type' => 'image',
                ]);
            }
            else {
                $firstStatus = Status::with('media')->find($request->status_ids[0]);

                $firstImage = $firstStatus->media->firstWhere('type', 'image');

                if ($firstImage) {
                    $highlight->media()->create([
                        'path' => $firstImage->path,
                        'type' => 'image',
                    ]);
                }
                //else: If it's a video, skip (future support)
            }

            DB::commit();

            return response()->json(['message' => 'Highlight created successfully.'], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create highlight.', 'details' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $user = Auth::user();

        $highlight = Highlight::with([
            'statuses.media',
            'media'
        ])
                              ->where('id', $id)
                              ->where('user_id', $user->id)
                              ->firstOrFail();

        $coverUrl = null;

        if ($highlight->media) {
            $coverUrl = url("storage/{$highlight->media->path}");
        } else {
            $firstImageStatus = $highlight->statuses->firstWhere(fn($status) =>
                $status->media && $status->media->type === 'image'
            );

            if ($firstImageStatus && $firstImageStatus->media) {
                $coverUrl = url("storage/{$firstImageStatus->media->path}");
            }
        }

        return response()->json([
            'highlight' => [
                'id' => $highlight->id,
                'text' => $highlight->text,
                'cover' => $coverUrl,
                'statuses' => $highlight->statuses->map(function ($status)use($highlight) {
                    $addedAt = StatusHighlights::where('highlight_id',$highlight->id)->where('status_id',$status->id)->value('added_at');

                    return [
                        'id' => $status->id,
                        'text' => $status->text,
                        'expiration_date' => $status->expiration_date,
                        'media' => $status->media ? url("storage/{$status->media->path}") : null,
                        'type' => $status->media->type ?? null,
                        'added_at' =>$addedAt
                    ];
                }),
            ]
        ]);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $highlight = $user->highlights()->findOrFail($id);

        $highlight->statuses()->detach();
        $highlight->media()?->delete();
        $highlight->delete();

        return response()->json(['message' => 'Highlight deleted.']);
    }
}
