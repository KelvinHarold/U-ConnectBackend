<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\Setting;
use App\Helpers\SellerSubscriptionRequestNotificationHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user();

        $currentSubscription = $seller->subscriptions()->whereIn('status', ['active', 'pending'])->latest()->first();
        $history = $seller->subscriptions()->latest()->get();

        return response()->json([
            'current_subscription' => $currentSubscription,
            'history' => $history,
            'is_active' => $seller->hasActiveSubscription(),
            'price' => Setting::getVal('subscription_price', '5000'),
            'payment_number' => Setting::getVal('subscription_payment_number', '0000000000'),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'payment_proof' => 'required|image|max:2048',
            ]);

            $seller = $request->user();

            // Check if there is already a pending request
            $pending = $seller->subscriptions()->where('status', 'pending')->first();
            if ($pending) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'You already have a pending subscription request.'
                ], 422);
            }

            $path = $request->file('payment_proof')->store('payment_proofs', 'public');

            $price = Setting::getVal('subscription_price', '50.00');

            $subscription = $seller->subscriptions()->create([
                'status' => 'pending',
                'payment_proof' => $path,
                'amount' => $price,
            ]);

            // Load seller relationship for notification
            $subscription->load('seller');

            // ========== SEND NOTIFICATION TO ALL ADMINS ==========
            SellerSubscriptionRequestNotificationHelper::notifyAdminsOfNewRequest($subscription);

            return response()->json([
                'status' => 'success', 
                'message' => 'Subscription request submitted successfully. Admin has been notified.',
                'data' => $subscription
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Subscription request failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit subscription request. Please try again.'
            ], 500);
        }
    }
}