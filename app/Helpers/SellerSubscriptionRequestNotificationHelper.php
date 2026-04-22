<?php

namespace App\Helpers;

use App\Models\Subscription;
use App\Services\NotificationService;

class SellerSubscriptionRequestNotificationHelper
{
    /**
     * Send notification to all admins when a seller submits a new subscription request
     */
    public static function notifyAdminsOfNewRequest($subscription)
    {
        $notificationService = app(NotificationService::class);
        $seller = $subscription->seller;
        
        $body = "A new seller subscription request needs your review.\n\n" .
                "Seller: {$seller->name}\n" .
                "Email: {$seller->email}\n" .
                "Phone: {$seller->phone}\n" .
                "Amount: " . number_format($subscription->amount, 2) . "\n" .
                "Requested at: " . $subscription->created_at->format('Y-m-d H:i:s');
        
        return $notificationService->sendToRole('admin', [
            'type' => 'seller_subscription_request',
            'title' => 'New Seller Subscription Request! 📝',
            'body' => $body,
            'data' => [
                'subscription_id' => $subscription->id,
                'seller_id' => $seller->id,
                'seller_name' => $seller->name,
                'seller_email' => $seller->email,
                'seller_phone' => $seller->phone,
                'amount' => $subscription->amount,
                'payment_proof' => $subscription->payment_proof,
                'status' => $subscription->status,
                'requested_at' => $subscription->created_at->toDateTimeString(),
            ],
        ]);
    }
    
    /**
     * Send notification to seller when admin approves their subscription
     */
    public static function notifySellerOfApproval($subscription)
    {
        $notificationService = app(NotificationService::class);
        $seller = $subscription->seller;
        
        if (!$seller) {
            return null;
        }
        
        return $notificationService->sendToUser($seller, [
            'type' => 'subscription_approved',
            'title' => 'Subscription Approved! ✅',
            'body' => "Your subscription request has been approved! You now have full seller access for 30 days.\n\n" .
                      "Start selling your products and reaching more customers!",
            'data' => [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toDateTimeString(),
                'ends_at' => $subscription->ends_at?->toDateTimeString(),
                'approved_at' => now()->toDateTimeString(),
            ],
        ]);
    }
    
    /**
     * Send notification to seller when admin rejects their subscription
     */
    public static function notifySellerOfRejection($subscription)
    {
        $notificationService = app(NotificationService::class);
        $seller = $subscription->seller;
        
        if (!$seller) {
            return null;
        }
        
        return $notificationService->sendToUser($seller, [
            'type' => 'subscription_rejected',
            'title' => 'Subscription Request Update ❌',
            'body' => "Your subscription request has been reviewed and cannot be approved at this time.\n\n" .
                      "Status: Rejected\n\n" .
                      "Please contact support for more information or submit a new request.",
            'data' => [
                'subscription_id' => $subscription->id,
                'status' => $subscription->status,
                'rejected_at' => now()->toDateTimeString(),
            ],
        ]);
    }
    
    /**
     * Send notification to seller when subscription is about to expire (7 days left)
     */
    public static function notifySellerOfExpiringSubscription($subscription)
    {
        $notificationService = app(NotificationService::class);
        $seller = $subscription->seller;
        
        if (!$seller) {
            return null;
        }
        
        $daysLeft = now()->diffInDays($subscription->ends_at);
        
        return $notificationService->sendToUser($seller, [
            'type' => 'subscription_expiring',
            'title' => 'Subscription Expiring Soon! ⏰',
            'body' => "Your subscription will expire in {$daysLeft} days.\n\n" .
                      "Renew now to continue selling without interruption.",
            'data' => [
                'subscription_id' => $subscription->id,
                'ends_at' => $subscription->ends_at?->toDateTimeString(),
                'days_left' => $daysLeft,
            ],
        ]);
    }
    
    /**
     * Send notification to seller when subscription has expired
     */
    public static function notifySellerOfExpiredSubscription($subscription)
    {
        $notificationService = app(NotificationService::class);
        $seller = $subscription->seller;
        
        if (!$seller) {
            return null;
        }
        
        return $notificationService->sendToUser($seller, [
            'type' => 'subscription_expired',
            'title' => 'Subscription Expired ⚠️',
            'body' => "Your subscription has expired.\n\n" .
                      "Please renew your subscription to continue selling on U-Connect.",
            'data' => [
                'subscription_id' => $subscription->id,
                'expired_at' => now()->toDateTimeString(),
            ],
        ]);
    }
}