<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\Subscription;

class SubscriptionApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_approved',
            'title' => 'Subscription Approved',
            'message' => 'Your subscription payment has been verified and your account is now active until ' . $this->subscription->ends_at->format('M d, Y') . '.',
            'subscription_id' => $this->subscription->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'subscription_approved',
            'title' => 'Subscription Approved',
            'message' => 'Your subscription payment has been verified and your account is now active.',
        ]);
    }
}
