<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>New Order #{{ $order->order_number }}</title>
</head>

<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <div style="text-align: center; margin-bottom: 20px;">
        <img src="{{ $message->embed(public_path('images/logo.png')) }}"
            alt="U-Connect Logo"
            style="max-width: 150px; margin-bottom: 10px;">

        <h2 style="color: #5C352C; font-size: 24px; text-transform: uppercase;">New Order Received! 🛍️</h2>
        <p style="font-size: 16px; color: #555;">You have received a new order from <strong>{{ $order->buyer->name }}</strong>.</p>
    </div>

    <div style="background: #fdfdfd; padding: 20px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #5C352C; border-bottom: 1px solid #eee; padding-bottom: 10px; text-transform: uppercase; font-size: 16px;">Order Summary</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 5px 0;"><strong>Order Number:</strong></td>
                <td style="padding: 5px 0; text-align: right;">{{ $order->order_number }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 0;"><strong>Date:</strong></td>
                <td style="padding: 5px 0; text-align: right;">{{ $order->created_at->format('Y-m-d H:i') }}</td>
            </tr>
            <tr>
                <td colspan="2" style="padding: 15px 0 5px 0;"><strong>Items Ordered:</strong></td>
            </tr>
            @foreach($order->items as $item)
            <tr>
                <td style="padding: 5px 0; color: #555;">&bull; {{ $item->quantity }}x {{ $item->product_name }}</td>
                <td style="padding: 5px 0; text-align: right; color: #555;">Tsh {{ number_format($item->subtotal) }}</td>
            </tr>
            @endforeach
            <tr>
                <td style="padding: 15px 0 5px 0; border-top: 1px dashed #eee;"><strong>Subtotal:</strong></td>
                <td style="padding: 15px 0 5px 0; border-top: 1px dashed #eee; text-align: right;">Tsh {{ number_format($order->subtotal) }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-top: 1px solid #eee; font-weight: bold; font-size: 16px; color: #5C352C;">Total:</td>
                <td style="padding: 10px 0; border-top: 1px solid #eee; text-align: right; font-weight: bold; font-size: 16px; color: #5C352C;">Tsh {{ number_format($order->total) }}</td>
            </tr>
        </table>
    </div>

    <div style="background: #fdfdfd; padding: 20px; border: 1px solid #eee; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin-top: 0; color: #5C352C; border-bottom: 1px solid #eee; padding-bottom: 10px; text-transform: uppercase; font-size: 16px;">Buyer & Delivery Details</h3>
        <p style="margin: 5px 0;"><strong>Name:</strong> {{ $order->buyer->name }}</p>
        <p style="margin: 5px 0;"><strong>Phone:</strong> {{ $order->buyer->phone ?? 'Not provided' }}</p>
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f5f5f5;">
            <strong>Delivery Address:</strong><br>
            <p style="margin: 5px 0; color: #555;">{{ $order->delivery_address }}</p>
        </div>
        @if($order->notes)
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #f5f5f5;">
            <strong>Order Notes:</strong><br>
            <p style="margin: 5px 0; color: #555;">{{ $order->notes }}</p>
        </div>
        @endif
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 15px; background: #fff8f5; border-radius: 8px; border: 1px dashed #f0c3b0;">
        <p style="margin: 0; color: #d65a31; font-weight: bold;">
            Please find the detailed order invoice (PDF) attached to this email.
        </p>
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #999;">
        <p>Thank you for doing business with U-Connect!</p>
    </div>

</body>

</html>