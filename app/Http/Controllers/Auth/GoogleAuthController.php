<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthController extends Controller
{
    public function handleGoogleToken(Request $request)
    {
        $idToken = $request->input('id_token');

        // Verify the token with Google
        $googleResponse = Http::get("https://oauth2.googleapis.com/tokeninfo", [
            'id_token' => $idToken,
        ]);

        if ($googleResponse->failed() || !isset($googleResponse['email'])) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $googleUser = $googleResponse->json();

        // Check if user already exists
        $user = User::where('email', $googleUser['email'])->first();
        $isNewUser = false;

        if (!$user) {
            // New user, create and verify
            $user = User::create([
                'name' => isset( $googleUser['name'] ) ? $googleUser['name']
                    : 'Unknown',
                'email' => $googleUser['email'],
                'google_id' => $googleUser['sub'],
                'email_verified_at' => Carbon::now(),
            ]);

            $isNewUser = true;
        } else {
            // Existing user, update info if needed
            $user->update([
                'name' => isset( $googleUser['name'] ) ? $googleUser['name']
                    : $user->name,
                'google_id' => $googleUser['sub'],
            ]);
        }

        // Create JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => $isNewUser ? 'Registered successfully' : 'Login successful',
            'token' => $token,
            'user' => $user,
        ]);
    }
}
