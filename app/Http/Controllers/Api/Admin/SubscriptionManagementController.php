<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Helpers\SubscriptionNotificationHelper;
use Illuminate\Support\Facades\Log;

class SubscriptionManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::with('seller')->latest();

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $subscriptions = $query->paginate(25);
        return response()->json(['status' => 'success', 'data' => $subscriptions]);
    }

    public function approve($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            
            if ($subscription->status !== 'pending') {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Only pending subscriptions can be approved.'
                ], 400);
            }

            $subscription->update([
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addMonth()
            ]);

            // ========== SEND NOTIFICATION TO SELLER ==========
            SubscriptionNotificationHelper::sendApprovedNotification($subscription);

            return response()->json([
                'status' => 'success', 
                'message' => 'Subscription approved successfully. Seller has been notified.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Subscription approval failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve subscription.'
            ], 500);
        }
    }

    public function reject($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);

            if ($subscription->status !== 'pending') {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Only pending subscriptions can be rejected.'
                ], 400);
            }

            $subscription->update(['status' => 'rejected']);

            // ========== SEND NOTIFICATION TO SELLER ==========
            SubscriptionNotificationHelper::sendRejectedNotification($subscription);

            return response()->json([
                'status' => 'success', 
                'message' => 'Subscription rejected successfully. Seller has been notified.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Subscription rejection failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject subscription.'
            ], 500);
        }
    }

    public function sellerStatus()
    {
        try {
            $sellers = \App\Models\User::role('seller')
                ->with(['subscriptions' => function($query) {
                    $query->latest();
                }])
                ->get();

            $data = $sellers->map(function($seller) {
                $latestSub = $seller->subscriptions->first();
                return [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'store_name' => $seller->store_name,
                    'email' => $seller->email,
                    'status' => $latestSub ? $latestSub->status : 'none',
                    'ends_at' => $latestSub ? $latestSub->ends_at : null,
                    'last_subscription' => $latestSub ? $latestSub->created_at : null
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch seller statuses: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch seller statuses.'
            ], 500);
        }
    }
}