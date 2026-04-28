<?php
// app/Http/Controllers/Api/ProfileController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    protected $imageService;

    public function __construct(\App\Services\ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Get user profile based on role
     */
    public function show()
    {
        $user = auth()->user();
        $user->role = $user->getRoleNames()->first();
        
        $response = [
            'user' => $user,
            'role' => $user->role
        ];
        
        // Add role-specific statistics
        if ($user->role === 'seller') {
            $response['stats'] = [
                'total_products' => $user->products()->count(),
                'total_orders' => $user->ordersAsSeller()->count(),
                'total_revenue' => $user->ordersAsSeller()->where('status', 'delivered')->sum('total'),
                'rating' => 4.5,
            ];
        } elseif ($user->role === 'buyer') {
            $response['stats'] = [
                'total_orders' => $user->ordersAsBuyer()->count(),
                'total_spent' => $user->ordersAsBuyer()->where('status', 'delivered')->sum('total'),
                'pending_orders' => $user->ordersAsBuyer()->where('status', 'pending')->count(),
                'delivered_orders' => $user->ordersAsBuyer()->where('status', 'delivered')->count(),
            ];
        }
        
        return response()->json($response);
    }

    /**
     * Update user profile
     */
  public function update(Request $request)
{
    $user = auth()->user();
    
    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,' . $user->id,
        'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
        'address' => 'sometimes|string',
        'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Update only user table fields
    $userData = $request->only(['name', 'email', 'phone', 'address']);
    
    // Handle profile photo
    if ($request->hasFile('profile_photo')) {
        if ($user->profile_photo) {
            $this->imageService->delete($user->profile_photo);
        }
        $userData['profile_photo'] = $this->imageService->compressAndSave($request->file('profile_photo'), 'profile-photos');
    }
    
    $user->update($userData);

    // If user is seller, update seller table separately
    if ($user->hasRole('seller') && $request->has(['store_name', 'store_description', 'store_logo'])) {
        $seller = $user->seller; // You need to have seller relationship
        if ($seller) {
            $sellerData = $request->only(['store_name', 'store_description']);
            
            if ($request->hasFile('store_logo')) {
                if ($seller->store_logo) {
                    $this->imageService->delete($seller->store_logo);
                }
                $sellerData['store_logo'] = $this->imageService->compressAndSave($request->file('store_logo'), 'store-logos');
            }
            
            $seller->update($sellerData);
        }
    }

    return response()->json([
        'message' => 'Profile updated successfully',
        'user' => $user->fresh(),
        'profile_photo' => $user->profile_photo
    ]);
}

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = auth()->user();
        
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }
        
        $user->password = Hash::make($request->new_password);
        $user->save();
        
        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }
}