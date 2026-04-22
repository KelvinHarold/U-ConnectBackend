<?php

use App\Services\NotificationService;

if (!function_exists('notify')) {
    function notify()
    {
        return app(NotificationService::class);
    }
}