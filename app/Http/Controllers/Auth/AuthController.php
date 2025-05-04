<?php

namespace App\Http\Controllers\Auth;


use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordEmails;
use App\Mail\VerifyEmails;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);



        $code = mt_rand(1000, 9999);
        $user->verification_code = $code;
        $user->save();

        Mail::to($user->email)->send(new VerifyEmails($code));


        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        $user = User::where('email', $request->email)->first();

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:4',
        ]);

        $user = User::where('email', $request->email)
                    ->where('verification_code', $request->code)
                    ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid code or email'], 400);
        }

        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->save();

        return response()->json(['message' => 'Email verified successfully']);
    }

    public function requestResetCode(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $code = mt_rand(1000, 9999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make(Str::random(60)),
                'code' => $code,
                'created_at' => now()
            ]
        );

        Mail::to($request->email)->send(new ResetPasswordEmails($code));

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:4',
            'password' => 'required|min:6',
        ]);

        $reset = DB::table('password_reset_tokens')
                   ->where('email', $request->email)
                   ->where('code', $request->code)
                   ->first();

        if (!$reset) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful.']);
    }
}

