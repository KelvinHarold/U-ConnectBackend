<?php

namespace App\Helpers;

use App\Models\User;
use App\Services\NotificationService;

class AuthenticationNotificationHelper
{
    protected $notificationService;
    
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    
    /**
     * Send welcome notification to new user
     */
    public static function sendWelcomeNotification($user, $role)
    {
        $notificationService = app(NotificationService::class);
        
        return $notificationService->sendToUser($user, [
            'type' => 'welcome',
            'title' => 'Welcome to U-Connect! 🎉',
            'body' => "Thank you for joining U-Connect, {$user->name}! We're excited to have you on board.",
            'data' => [
                'user_id' => $user->id,
                'user_role' => $role,
            ],
            'actions' => [
                ['label' => 'Go to Dashboard', 'url' => "/{$role}/dashboard"],
                ['label' => 'Complete Profile', 'url' => "/profile"],
            ],
        ]);
    }
    
    /**
     * Send notification to admins when new user registers
     */
    public static function sendNewUserNotificationToAdmins($user, $role)
    {
        $notificationService = app(NotificationService::class);
        
        // Get role name for display (buyer/seller)
        $roleDisplay = ucfirst($role);
        
        // Send to all admins using your existing sendToRole method
        return $notificationService->sendToRole('admin', [
            'type' => 'new_registration',
            'title' => 'New User Registration! 🎉',
            'body' => "A new {$role} has joined U-Connect!\n\n" .
                      "Name: {$user->name}\n" .
                      "Email: {$user->email}\n" .
                      "Phone: {$user->phone}\n" .
                      "Role: {$roleDisplay}",
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'user_phone' => $user->phone,
                'user_role' => $role,
                'registered_at' => now()->toDateTimeString(),
            ],
            'actions' => [
                ['label' => 'View User Profile', 'url' => "/admin/users/{$user->id}"],
                ['label' => 'View All Users', 'url' => "/admin/users"],
            ],
        ]);
    }
    
    /**
     * Send notification to user when their account is activated
     */
    public static function sendAccountActivatedNotification($user)
    {
        $notificationService = app(NotificationService::class);
        
        return $notificationService->sendToUser($user, [
            'type' => 'account_activated',
            'title' => 'Account Activated ✅',
            'body' => "Hello {$user->name}, your account has been activated. You can now log in and start using U-Connect!",
            'data' => [
                'user_id' => $user->id,
                'activated_at' => now()->toDateTimeString(),
            ],
            'actions' => [
                ['label' => 'Login Now', 'url' => "/login"],
                ['label' => 'Go to Dashboard', 'url' => "/dashboard"],
            ],
        ]);
    }
    
    /**
     * Send notification to user when their account is deactivated
     */
    public static function sendAccountDeactivatedNotification($user)
    {
        $notificationService = app(NotificationService::class);
        
        return $notificationService->sendToUser($user, [
            'type' => 'account_deactivated',
            'title' => 'Account Deactivated ⚠️',
            'body' => "Hello {$user->name}, your account has been deactivated. Please contact support for more information.",
            'data' => [
                'user_id' => $user->id,
                'deactivated_at' => now()->toDateTimeString(),
            ],
            'actions' => [
                ['label' => 'Contact Support', 'url' => "/support"],
            ],
        ]);
    }
    
    /**
     * Send password reset notification
     */
    public static function sendPasswordResetNotification($user, $resetToken)
    {
        $notificationService = app(NotificationService::class);
        
        return $notificationService->sendToUser($user, [
            'type' => 'password_reset',
            'title' => 'Password Reset Request 🔐',
            'body' => "Hello {$user->name}, we received a request to reset your password. Click the button below to proceed.",
            'data' => [
                'user_id' => $user->id,
                'reset_token' => $resetToken,
                'requested_at' => now()->toDateTimeString(),
            ],
            'actions' => [
                ['label' => 'Reset Password', 'url' => "/password/reset/{$resetToken}"],
                ['label' => 'Ignore if not requested', 'url' => "#"],
            ],
        ]);
    }
    
    /**
     * Send login alert for new device
     */
    public static function sendNewDeviceLoginAlert($user, $deviceInfo, $location)
    {
        $notificationService = app(NotificationService::class);
        
        return $notificationService->sendToUser($user, [
            'type' => 'new_device_login',
            'title' => 'New Login Detected 🔔',
            'body' => "Hello {$user->name}, someone logged into your account from a new device.\n\n" .
                      "Device: {$deviceInfo}\n" .
                      "Location: {$location}\n" .
                      "Time: " . now()->format('Y-m-d H:i:s') . "\n\n" .
                      "If this wasn't you, please secure your account immediately.",
            'data' => [
                'user_id' => $user->id,
                'device_info' => $deviceInfo,
                'location' => $location,
                'login_time' => now()->toDateTimeString(),
            ],
            'actions' => [
                ['label' => 'Secure Account', 'url' => "/security"],
                ['label' => 'View Login History', 'url' => "/security/login-history"],
            ],
        ]);
    }
    
    /**
     * Send role changed notification
     */
    public static function sendRoleChangedNotification($user, $oldRole, $newRole)
    {
        $notificationService = app(NotificationService::class);
        
        return $notificationService->sendToUser($user, [
            'type' => 'role_changed',
            'title' => 'Account Role Updated 🔄',
            'body' => "Hello {$user->name}, your account role has been changed from {$oldRole} to {$newRole}.",
            'data' => [
                'user_id' => $user->id,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'updated_at' => now()->toDateTimeString(),
            ],
            'actions' => [
                ['label' => 'View Dashboard', 'url' => "/{$newRole}/dashboard"],
                ['label' => 'Learn More', 'url' => "/roles/guide"],
            ],
        ]);
    }
    
    /**
     * Send email verification reminder
     */
    public static function sendEmailVerificationReminder($user)
    {
        $notificationService = app(NotificationService::class);
        
        return $notificationService->sendToUser($user, [
            'type' => 'email_verification',
            'title' => 'Verify Your Email Address 📧',
            'body' => "Hello {$user->name}, please verify your email address to get full access to U-Connect features.",
            'data' => [
                'user_id' => $user->id,
            ],
            'actions' => [
                ['label' => 'Verify Email', 'url' => "/email/verify"],
                ['label' => 'Resend Link', 'url' => "/email/resend"],
            ],
        ]);
    }
}