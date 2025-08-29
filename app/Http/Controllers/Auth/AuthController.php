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
use App\Services\EventPublisher;
class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8'
        ]);
        $user = User::create([
            'email' => $validated['email'],
            'password' => bcrypt($validated['password'])
        ]);

        $code = mt_rand(1000, 9999);
        $user->verification_code = $code;
        $user->verification_code_sent_at = now();
        $user->save();

        $user->savedPost()->create();

        app(EventPublisher::class)->publishEvent('UserCreated',[
                'id' => $user->id,
                'email' => $user->email,
        ]);
        try{
            Mail::to($user->email)->send(new VerifyEmails($code));
        } catch(\Exception $e){

        }

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


        $user = User::with('media')->where('email', $request->email)->first();

        if(!$user->email_verified_at){
            return response()->json(['message'=>'Email Not Verified'],400);
        }
       return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->media ? url("storage/{$user->media->path}") : null,
                'is_private' =>$user->is_private,
            ],
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

        if (!$user || now()->diffInMinutes($user->verification_code_sent_at) > 60) {
            return response()->json(['message' => 'Invalid or expired code'], 400);
        }

        $user->email_verified_at = now();
        $user->verification_code = null;
        $user->verification_code_sent_at = null;
        $user->save();

        return response()->json(['message' => 'Email verified successfully']);
    }

    public function requestCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'reset_password' => 'nullable|boolean',
            'verify_email' => 'nullable|boolean',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($request->reset_password) {
            $code = mt_rand(1000, 9999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make(Str::random(60)),
                    'code' => $code,
                    'created_at' => now(),
                ]
            );

            Mail::to($user->email)->send(new ResetPasswordEmails($code));
        }

        if ($request->verify_email) {
            $code = mt_rand(1000, 9999);

            $user->verification_code = $code;
            $user->verification_code_sent_at = now();
            $user->save();

            Mail::to($user->email)->send(new VerifyEmails($code));
        }

        return response()->json(['message' => 'Code sent successfully.']);
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

