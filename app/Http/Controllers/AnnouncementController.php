<?php
// app/Http/Controllers/AnnouncementController.php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Helpers\AnnouncementNotificationHelper;

class AnnouncementController extends Controller
{
    // Get all announcements (for admin)
    public function index(Request $request)
    {
        $query = Announcement::with(['creator:id,name,email']);
        
        // Apply filters
        if ($request->has('status') && $request->status !== '' && $request->status !== null) {
            $query->where('status', $request->status);
        }
        
        // Apply audience filter
        if ($request->has('audience') && $request->audience !== '' && $request->audience !== null) {
            $query->where('audience', $request->audience);
        }
        
        // Apply search filter
        if ($request->has('search') && $request->search !== '' && $request->search !== null) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('content', 'like', '%' . $request->search . '%');
            });
        }
        
        $announcements = $query->orderBy('created_at', 'desc')->paginate(15);
        
        return response()->json($announcements);
    }

    // Get published announcements for users (filtered by audience based on user role)
    public function getPublishedAnnouncements(Request $request)
    {
        // Get the authenticated user
        $user = auth()->user();
        
        // Debug: Log user info
        Log::info('User accessing announcements:', [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'user_type' => $user?->user_type,
            'all_user_attributes' => $user?->toArray()
        ]);
        
        // Get user role with multiple possible field names
        $userRole = $this->getUserRole($user);
        
        // Debug: Log determined role
        Log::info('Determined user role for filtering:', ['role' => $userRole]);
        
        // Get published announcements
        $query = Announcement::with(['creator:id,name'])
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
        
        // Apply audience filter
        if ($userRole) {
            // If user has a specific role, show 'all' announcements + their role-specific announcements
            $query->where(function($q) use ($userRole) {
                $q->where('audience', 'all')
                  ->orWhere('audience', $userRole);
            });
        } else {
            // If no role detected (guest or unknown), only show 'all' announcements
            $query->where('audience', 'all');
        }
        
        $announcements = $query->orderBy('published_at', 'desc')->get();
        
        // Debug: Log results
        Log::info('Announcements found:', [
            'count' => $announcements->count(),
            'announcements' => $announcements->map(function($a) {
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'audience' => $a->audience,
                    'status' => $a->status,
                    'published_at' => $a->published_at
                ];
            })->toArray()
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $announcements,
            'debug' => [
                'user_role_detected' => $userRole,
                'user_id' => $user?->id,
                'announcements_count' => $announcements->count()
            ]
        ]);
    }

    // Helper method to get user role - CHECK MULTIPLE POSSIBLE FIELDS
    private function getUserRole($user)
    {
        if (!$user) {
            Log::warning('No authenticated user found');
            return null;
        }
        
        // Check for role in different possible fields
        // Adjust these based on your actual database schema
        
        // Method 1: Direct role attribute
        if (isset($user->role)) {
            $role = strtolower($user->role);
            Log::info('Found role in $user->role:', ['role' => $role]);
            if ($role === 'buyer') return 'buyers';
            if ($role === 'seller') return 'sellers';
        }
        
        // Method 2: user_type attribute
        if (isset($user->user_type)) {
            $userType = strtolower($user->user_type);
            Log::info('Found role in $user->user_type:', ['user_type' => $userType]);
            if ($userType === 'buyer') return 'buyers';
            if ($userType === 'seller') return 'sellers';
        }
        
        // Method 3: Check through relationship (if you have a roles table)
        if (method_exists($user, 'roles') && $user->roles()->exists()) {
            $roleName = strtolower($user->roles->first()->name);
            Log::info('Found role through relationship:', ['role' => $roleName]);
            if ($roleName === 'buyer') return 'buyers';
            if ($roleName === 'seller') return 'sellers';
        }
        
        // Method 4: Check User model for type property
        if (method_exists($user, 'getRoleAttribute')) {
            $role = strtolower($user->getRoleAttribute());
            Log::info('Found role through getRoleAttribute:', ['role' => $role]);
            if ($role === 'buyer') return 'buyers';
            if ($role === 'seller') return 'sellers';
        }
        
        // If no role found, log all user attributes for debugging
        Log::warning('No role found for user. User attributes:', [
            'user_id' => $user->id,
            'attributes' => array_keys($user->getAttributes()),
            'all_data' => $user->toArray()
        ]);
        
        return null;
    }

    // Show single announcement
    public function show(Announcement $announcement)
    {
        return response()->json([
            'success' => true,
            'data' => $announcement->load('creator')
        ]);
    }

    // Store new announcement
  public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'content' => 'required|string',
        'status' => 'required|in:draft,published',
        'audience' => 'required|in:all,buyers,sellers',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $announcement = Announcement::create([
        'title' => $request->title,
        'content' => $request->content,
        'status' => $request->status,
        'audience' => $request->audience,
        'published_at' => $request->status === 'published' ? now() : null,
        'created_by' => auth()->id(),
    ]);

    // ========== SEND NOTIFICATIONS USING YOUR EXISTING SERVICE ==========
    if ($request->status === 'published') {
        AnnouncementNotificationHelper::sendAnnouncementNotifications($announcement);
        
        return response()->json([
            'success' => true,
            'message' => 'Announcement published and notifications sent to target audience',
            'data' => $announcement->load('creator')
        ], 201);
    }

    return response()->json([
        'success' => true,
        'message' => 'Announcement saved as draft',
        'data' => $announcement->load('creator')
    ], 201);
}

    // Update announcement
   public function update(Request $request, Announcement $announcement)
{
    $validator = Validator::make($request->all(), [
        'title' => 'sometimes|string|max:255',
        'content' => 'sometimes|string',
        'status' => 'sometimes|in:draft,published',
        'audience' => 'sometimes|in:all,buyers,sellers',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $data = $request->only(['title', 'content', 'status', 'audience']);
    
    // Check if we're publishing a draft for the first time
    $wasDraft = $announcement->status === 'draft';
    $isNowPublished = $request->has('status') && $request->status === 'published';
    
    // If status changed to published and no published_at, set it now
    if ($isNowPublished && !$announcement->published_at) {
        $data['published_at'] = now();
    }
    
    $announcement->update($data);

    // ========== SEND NOTIFICATIONS IF PUBLISHING A DRAFT ==========
    if ($wasDraft && $isNowPublished) {
        AnnouncementNotificationHelper::sendAnnouncementNotifications($announcement);
        
        return response()->json([
            'success' => true,
            'message' => 'Announcement published and notifications sent to target audience',
            'data' => $announcement->fresh('creator')
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Announcement updated successfully',
        'data' => $announcement->fresh('creator')
    ]);
}

    // Delete announcement
    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully'
        ]);
    }

    // Bulk update status
   public function bulkUpdateStatus(Request $request)
{
    $validator = Validator::make($request->all(), [
        'announcement_ids' => 'required|array',
        'announcement_ids.*' => 'exists:announcements,id',
        'status' => 'required|in:draft,published'
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $announcements = Announcement::whereIn('id', $request->announcement_ids)
        ->where('status', 'draft')
        ->get();
    
    foreach ($announcements as $announcement) {
        $announcement->update([
            'status' => $request->status,
            'published_at' => now()
        ]);
        
        // Send notifications for each announcement
        AnnouncementNotificationHelper::sendAnnouncementNotifications($announcement);
    }

    return response()->json([
        'success' => true,
        'message' => $announcements->count() . ' announcements published and notifications sent'
    ]);
}
}