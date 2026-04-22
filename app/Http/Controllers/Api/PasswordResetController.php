<?php
// app/Http/Controllers/Api/PasswordResetController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\DB;

class PasswordResetController extends Controller
{
    // Send password reset link
    public function sendResetLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email'
        ], [
            'email.exists' => 'We could not find a user with that email address.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();
            
            // Generate token
            $token = Str::random(64);
            
            // Store token in password_reset_tokens table
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                ['token' => $token, 'created_at' => now()]
            );
            
            // Send email
            Mail::to($request->email)->send(new ResetPasswordMail($token, $request->email));
            
            return response()->json([
                'success' => true,
                'message' => 'We have emailed your password reset link!'
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Password reset error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Unable to send reset link. Please try again later.'
            ], 500);
        }
    }

    // Reset password
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if token exists and is valid
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ], 400);
        }

        // Check if token is expired (60 minutes)
        $tokenCreatedAt = \Carbon\Carbon::parse($resetRecord->created_at);
        if ($tokenCreatedAt->diffInMinutes(now()) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.'
            ], 400);
        }

        // Update user password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Optionally, revoke all existing tokens to force re-login
        // $user->tokens()->delete();

        event(new PasswordReset($user));

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully! You can now login with your new password.'
        ], 200);
    }

    // Check if token is valid
    public function checkToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token.'
            ], 400);
        }

        // Check if token is expired (60 minutes)
        $tokenCreatedAt = \Carbon\Carbon::parse($resetRecord->created_at);
        if ($tokenCreatedAt->diffInMinutes(now()) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Reset token has expired. Please request a new one.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token is valid.'
        ], 200);
    }
}