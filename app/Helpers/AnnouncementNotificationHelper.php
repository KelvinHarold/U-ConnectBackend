<?php

namespace App\Helpers;

use App\Services\NotificationService;


class AnnouncementNotificationHelper
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Send notifications for an announcement based on its audience
     * Uses your existing NotificationService methods
     */
    public static function sendAnnouncementNotifications($announcement)
    {
        $notificationService = app(NotificationService::class);
        
        $data = [
            'type' => 'announcement',
            'title' => $announcement->title,
            'body' => $announcement->content,
            'data' => [
                'announcement_id' => $announcement->id,
                'announcement_title' => $announcement->title,
                'published_at' => $announcement->published_at?->toDateTimeString(),
                'audience' => $announcement->audience,
            ],
            'actions' => [
                ['label' => 'Read Announcement', 'url' => "/announcements/{$announcement->id}"],
                ['label' => 'View All Announcements', 'url' => "/announcements"],
            ],
        ];
        
        // Use your existing role-based notification methods
        switch ($announcement->audience) {
            case 'buyers':
                return $notificationService->sendToBuyers($data);
                
            case 'sellers':
                return $notificationService->sendToSellers($data);
                
            case 'all':
                // Send to both buyers and sellers
                $notificationService->sendToBuyers($data);
                $notificationService->sendToSellers($data);
                return true;
                
            default:
                return false;
        }
    }
}