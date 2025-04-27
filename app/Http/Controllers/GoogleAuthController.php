<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        $url = Socialite::driver('google')
                        ->stateless()
                        ->redirect()
                        ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                //'avatar' => $googleUser->getAvatar(),
            ]
        );
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }
}

