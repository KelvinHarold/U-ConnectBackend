<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Setting;

class SubscriptionSettingController extends Controller
{
    public function getSettings()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'price' => Setting::getVal('subscription_price', '50.00'),
                'payment_number' => Setting::getVal('subscription_payment_number', '0000000000')
            ]
        ]);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'price' => 'required|numeric|min:0',
            'payment_number' => 'required|string|max:20'
        ]);

        Setting::updateOrCreate(
            ['key' => 'subscription_price'],
            ['value' => $request->price, 'group' => 'subscription']
        );

        Setting::updateOrCreate(
            ['key' => 'subscription_payment_number'],
            ['value' => $request->payment_number, 'group' => 'subscription']
        );

        return response()->json(['status' => 'success', 'message' => 'Settings updated successfully.']);
    }
}
