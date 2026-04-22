<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    public function unreadCount()
    {
        $user = Auth::user();
        return response()->json([
            'success' => true,
            'unread_count' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'unread_count' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $count = $this->notificationService->markAllAsRead($user);

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marked as read",
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete all notifications for the authenticated user
     */
    public function destroyAll()
    {
        $user = Auth::user();
        $count = $user->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications deleted successfully",
            'unread_count' => 0,
        ]);
    }
}
