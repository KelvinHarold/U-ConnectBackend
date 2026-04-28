<?php
namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\AuthenticationNotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'required|in:buyer,seller',  // Admins role should not be placed for security reasons 
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'address' => $request->address,
            'is_active' => true,
        ]);

        $user->assignRole($request->role);

        // ========== SEND ALL NOTIFICATIONS USING HELPER ==========
        
        // 1. Send welcome notification to the new user
        AuthenticationNotificationHelper::sendWelcomeNotification($user, $request->role);
        
        // 2. Send notification to all admins about new registration
        AuthenticationNotificationHelper::sendNewUserNotificationToAdmins($user, $request->role);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'role' => $request->role,
            'token' => $token
        ], 201);
    }


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account is deactivated'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $userArray = $user->toArray();
        $userArray['profile_photo'] = $user->profile_photo ?? null;
        $userArray['store_name'] = $user->store_name ?? null;
        $userArray['store_description'] = $user->store_description ?? null;
        $userArray['store_logo'] = $user->store_logo ?? null;

        return response()->json([
            'message' => 'Login successful',
            'user' => $userArray,
            'role' => $user->getRoleNames()->first(),
            'token' => $token
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => 'Logged out successfully']);
    }
}