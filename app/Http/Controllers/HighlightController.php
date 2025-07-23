<?php
namespace App\Http\Controllers;

use App\Models\Highlight;
use App\Models\Status;
use App\Models\StatusHighlights;
use App\Models\User;
use Carbon\Carbon;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HighlightController extends Controller
{
    public function index(Request $request)
    {
        $authUser = Auth::user();
        $userId = $request->query('user_id');

        $user = $userId ? User::find($userId) : $authUser;

        $highlights = $user->highlights()->with(['media', 'statuses.media'])->get()->map(function ($highlight) {
            $cover = null;
            $textAsCover = null;

            if ($highlight->media) {
                $cover = url("storage/{$highlight->media->path}");
            } else {
                $firstStatus = $highlight->statuses->first();

                if ($firstStatus) {
                    if ($firstStatus->media) {
                        $cover = url("storage/{$firstStatus->media->path}");
                    } elseif (!$firstStatus->media && $firstStatus->text) {
                        $textAsCover = $firstStatus->text;
                    }
                }
            }

            return [
                'id' => $highlight->id,
                'text' => $highlight->text,
                'cover' =>  $cover,
                'statuses_count' => $highlight->statuses->count(),
                'text_as_cover' => $textAsCover
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

            foreach ($request->status_ids as $statusId) {
                $highlight->statuses()->attach($statusId, ['added_at' => Carbon::now()]);
            }

            if ($request->hasFile('cover')) {
                $path = $request->file('cover')->store('highlights_covers', 'public');

                $highlight->media()->create([
                    'path' => $path,
                    'type' => 'image',
                ]);
            } else {
                $firstStatus = Status::with('media')->find($request->status_ids[0]);

                if ($firstStatus && $firstStatus->media) {
                    $media = $firstStatus->media;

                    if ($media->type === 'image') {
                        $highlight->media()->create([
                            'path' => $media->path,
                            'type' => 'image',
                        ]);
                    } elseif ($media->type === 'video') {
                        $videoPath = storage_path("app/public/{$media->path}");
                        $frameFileName = 'highlights_covers/' . Str::random(40) . '.jpg';
                        $frameFullPath = storage_path("app/public/{$frameFileName}");

                        $ffmpeg = FFMpeg::create();
                        $video = $ffmpeg->open($videoPath);

                        $video->frame(TimeCode::fromSeconds(1))->save($frameFullPath);

                        $highlight->media()->create([
                            'path' => $frameFileName,
                            'type' => 'image',
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json(['message' => 'Highlight created successfully.'], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create highlight.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request)
    {
        $highlight = Highlight::with([
            'statuses.media',
            'media'
        ])
                              ->where('id', $request->id)
                              ->firstOrFail();

        $coverUrl = null;
        $textAsCover = null;

        if ($highlight->media) {
            $coverUrl = url("storage/{$highlight->media->path}");
        } else {
            $firstStatus = $highlight->statuses->first();

            if ($firstStatus) {
                if (!$firstStatus->media && $firstStatus->text) {
                    $textAsCover = $firstStatus->text;
                }
            }
        }

        return response()->json([
            'highlight' => [
                'id' => $highlight->id,
                'text' => $highlight->text,
                'cover' => $coverUrl,
                'text_as_cover' =>$textAsCover,
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

    public function setCover(Request $request)
    {
        $request->validate([
            'highlight_id' => 'required|exists:highlights,id',
            'cover' => 'required|image|max:5120'
        ]);

        $authUser = Auth::user();

        $highlight = Highlight::where('id', $request->highlight_id)
                              ->where('user_id', $authUser->id)
                              ->firstOrFail();


        if ($highlight->media) {
            Storage::disk('public')->delete($highlight->media->path);
            $highlight->media()->delete();
        }

        $path = $request->file('cover')->store('highlights_covers', 'public');

        $highlight->media()->create([
            'path' => $path,
            'type' => 'image',
        ]);

        $highlight->update(['cover_path' => $path]);

        $coverUrl = url("storage/{$path}");

        return response()->json([
            'message' => 'Cover set successfully',
            'highlight' => [
                'id' => $highlight->id,
                'text' => $highlight->text,
                'cover' => $coverUrl
            ]
        ]);
    }
}
