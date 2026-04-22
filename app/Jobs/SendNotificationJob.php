<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Notification;
use App\Events\NewNotificationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user, array $data)
    {
        $this->user = $user;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Re-resolve the user to ensure it's fresh (or just use the passed model instances)
            $user = $this->user;
            $data = $this->data;

            if (!$user) return;

            // Define icons and colors if not provided
            $icon = $data['icon'] ?? $this->getIconForType($data['type'] ?? 'general');
            $color = $data['color'] ?? $this->getColorForType($data['type'] ?? 'general');

            // 1. Create the notification in DB
            $notification = Notification::create([
                'notifiable_type' => get_class($user),
                'notifiable_id' => $user->id,
                'type' => $data['type'] ?? 'general',
                'title' => $data['title'],
                'body' => $data['body'] ?? '',
                'data' => $data['data'] ?? [],
                'actions' => $data['actions'] ?? [],
                'icon' => $icon,
                'color' => $color,
                'delivered_at' => now(),
            ]);

            // 2. Refresh the user's unread notifications count
            $unreadCount = $user->unreadNotifications()->count();

            // 3. Broadcast the notification over Pusher via Laravel Echo
            broadcast(new NewNotificationEvent($notification, $user->id, $unreadCount));
        } catch (\Exception $e) {
            Log::error('ProcessNotificationJob Failed: ' . $e->getMessage());
            // optionally re-throw to retry the job
            throw $e;
        }
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
}
