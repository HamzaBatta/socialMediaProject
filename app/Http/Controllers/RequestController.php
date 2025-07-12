<?php

namespace App\Http\Controllers;

use App\Models\Request;
use App\Models\User;
use App\Http\Requests\StoreRequestRequest;
use App\Http\Requests\UpdateRequestRequest;
use Illuminate\Support\Facades\Auth;

class RequestController extends Controller
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
    public function store(StoreRequestRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $authUser = Auth::user();


        $pendingRequests = $authUser
            ->requests()
            ->where('state', 'pending')
            ->with('creator.media')
            ->get();

        return response()->json([
            'pending_requests' => $pendingRequests->map(fn($request)=>[
                'id' => $request->id,
                'user_id' => $request->user_id,
                'requestable_id' => $request->requestable_id,
                'requestable_type' => $request->requestable_type,
                'state' => $request->state,
                'requested_at' => $request->requested_at,
                'responsed_at' => $request->responsed_at,
                'created_at' => $request->created_at,
                'updated_at' => $request->updated_at,
                'creator' =>[
                    'id' => $request->creator->id,
                    'name' => $request->creator->name,
                    'username' => $request->creator->username,
                    'avatar' => $request->creator->media ? url("storage/{$request->creator->media->path}") : null,
                    'is_private' => $request->creator->is_private,
                ]
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request)
    {
        //accepting a user request :
        $currentUser = User::findOrFail(Auth::id());
        $targetUser = User::findOrFail($request->targetId);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequestRequest $t,Request $request)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        //
    }
}
