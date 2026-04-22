<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\Subscription;

class SubscriptionRejected extends Notification implements ShouldQueue
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
            'type' => 'subscription_rejected',
            'title' => 'Subscription Rejected',
            'message' => 'Your subscription payment could not be verified. Please try again.',
            'subscription_id' => $this->subscription->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'subscription_rejected',
            'title' => 'Subscription Rejected',
            'message' => 'Your subscription payment could not be verified. Please try again.',
        ]);
    }
}
