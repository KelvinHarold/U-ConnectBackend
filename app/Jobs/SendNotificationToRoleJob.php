<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationToRoleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $roles;
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param string|array $roles
     * @param array $data
     */
    public function __construct($roles, array $data)
    {
        $this->roles = $roles;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(NotificationService $notificationService)
    {
        try {
            // Get users with the matching roles using chunking to save memory
            User::role($this->roles)->chunk(100, function ($users) use ($notificationService) {
                foreach ($users as $user) {
                    // Send to user (which will dispatch individual SendNotificationJob)
                    $notificationService->sendToUser($user, $this->data);
                }
            });
        } catch (\Exception $e) {
            Log::error('SendNotificationToRoleJob Failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
