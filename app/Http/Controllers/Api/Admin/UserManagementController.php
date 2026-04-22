<?php
// app/Http/Controllers/Api/Admin/UserManagementController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');
        
        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            $query->role($request->role);
        }
        
        // Filter by status - FIXED
        if ($request->has('status') && $request->status !== 'all') {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        // Search
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        $users = $query->latest()->paginate(5);
        
        // Transform to add role name to each user
        $users->getCollection()->transform(function($user) {
            $user->role = $user->getRoleNames()->first();
            return $user;
        });
        
        return response()->json($users);
    }

    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $user->role = $user->getRoleNames()->first();
        
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'sometimes|string|unique:users,phone,' . $id,
            'address' => 'sometimes|string',
            'role' => 'sometimes|in:admin,seller,buyer',
            'is_active' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user->update($request->only(['name', 'email', 'phone', 'address', 'is_active']));
        
        if ($request->has('role')) {
            $user->syncRoles([$request->role]);
        }
        
        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete your own account'], 403);
        }
        
        $user->delete();
        
        return response()->json(['message' => 'User deleted successfully']);
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent deactivating yourself
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot deactivate your own account'], 403);
        }
        
        $user->is_active = !$user->is_active;
        $user->save();
        
        return response()->json([
            'message' => 'User status updated',
            'is_active' => $user->is_active
        ]);
    }

    public function resetPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:8|confirmed',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();
        
        return response()->json(['message' => 'Password reset successfully']);
    }
}