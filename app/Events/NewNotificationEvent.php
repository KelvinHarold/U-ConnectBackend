<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;
    public $userId;
    public $unreadCount;

    public function __construct(Notification $notification, $userId, $unreadCount)
    {
        $this->notification = $notification;
        $this->userId = $userId;
        $this->unreadCount = $unreadCount;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user-notifications.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new-notification';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'data' => $this->notification->data,
            'actions' => $this->notification->actions,
            'icon' => $this->notification->icon,
            'color' => $this->notification->color,
            'created_at' => $this->notification->created_at->diffForHumans(),
            'read_at' => $this->notification->read_at,
            'unread_count' => $this->unreadCount,
        ];
    }
}