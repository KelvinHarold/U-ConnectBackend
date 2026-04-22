<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Events\NewNotificationEvent;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use App\Jobs\SendNotificationJob;

class NotificationService
{
    public function sendToUser($user, array $data): ?Notification
    {
        if (!$user) return null;
        
        // Dispatch the job to the queue instead of processing synchronously
        // This speeds up the API and offloads the Pusher broadcast + DB query to the background
        SendNotificationJob::dispatch($user, $data);
        
        // We're returning null here since the Notification object is created in the queue.
        // It's acceptable for most of the application since we normally don't depend on the
        // returned Notification instance right away.
        return null;
    }

    public function sendToUsers($users, array $data): array
    {
        $notifications = [];
        foreach ($users as $user) {
            $notifications[] = $this->sendToUser($user, $data);
        }
        return $notifications;
    }

    /**
     * Send notification to users with a specific role using Spatie Permission
     */
    public function sendToRole(string $role, array $data): array
    {
        // Dispatching a chunked job to prevent memory overload and blocking the request
        \App\Jobs\SendNotificationToRoleJob::dispatch($role, $data);
        return [];
    }

    /**
     * Send notification to users with multiple roles
     */
    public function sendToRoles(array $roles, array $data): array
    {
        \App\Jobs\SendNotificationToRoleJob::dispatch($roles, $data);
        return [];
    }
    
    public function sendToAdmins(array $data): array
    {
        return $this->sendToRole('admin', $data);
    }

    public function sendToSellers(array $data): array
    {
        return $this->sendToRole('seller', $data);
    }

    public function sendToBuyers(array $data): array
    {
        return $this->sendToRole('buyer', $data);
    }

    public function sendToUserId(int $userId, array $data): ?Notification
    {
        $user = User::find($userId);
        return $user ? $this->sendToUser($user, $data) : null;
    }

    /**
     * Send notification to all users except those with specific roles
     */
    public function sendToAllExceptRoles(array $excludeRoles, array $data): array
    {
        $users = User::whereDoesntHave('roles', function($query) use ($excludeRoles) {
            $query->whereIn('name', $excludeRoles);
        })->get();
        
        return $this->sendToUsers($users, $data);
    }

    /**
     * Send notification to specific users by email
     */
    public function sendToEmails(array $emails, array $data): array
    {
        $users = User::whereIn('email', $emails)->get();
        return $this->sendToUsers($users, $data);
    }

    private function getIconForType(string $type): string
    {
        return match ($type) {
            'category_created', 'category_updated', 'category_deleted' => 'folder',
            'product_created', 'product_updated', 'product_deleted' => 'package',
            'order_placed', 'order_status_updated' => 'shopping-cart',
            'payment_received', 'payment_failed' => 'credit-card',
            'user_registered', 'role_updated' => 'user',
            'inventory_alert', 'low_stock_alert' => 'alert-triangle',
            'promotion_created' => 'gift',
            default => 'bell',
        };
    }

    private function getColorForType(string $type): string
    {
        return match ($type) {
            'category_created', 'product_created', 'user_registered' => 'green',
            'category_updated', 'product_updated', 'role_updated' => 'blue',
            'category_deleted', 'product_deleted', 'payment_failed' => 'red',
            'order_placed', 'payment_received' => 'purple',
            'inventory_alert', 'low_stock_alert' => 'orange',
            default => 'gray',
        };
    }

    public function getUnreadCount($user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAllAsRead($user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function getUserNotifications($user, int $limit = 50)
    {
        return $user->notifications()->paginate($limit);
    }

    public function getRecentUnread($user, int $limit = 10)
    {
        return $user->unreadNotifications()->limit($limit)->get();
    }

    public function deleteNotification(Notification $notification): bool
    {
        return $notification->delete();
    }

    public function deleteAllUserNotifications($user): int
    {
        return $user->notifications()->delete();
    }

    public function deleteReadNotifications($user): int
    {
        return $user->readNotifications()->delete();
    }

    public function deleteOldNotifications(int $days = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))->delete();
    }
}