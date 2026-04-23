<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order #{{ $order->order_number }}</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; 
            color: #333; 
            line-height: 1.5; 
            font-size: 14px; 
            margin: 0;
            padding: 20px;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #5C352C; 
            padding-bottom: 20px; 
        }
        .logo {
            max-width: 200px;
            margin-bottom: 10px;
        }
        .header h1 { 
            color: #5C352C; 
            margin: 0; 
            font-size: 28px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .row { 
            width: 100%; 
            margin-bottom: 30px; 
            clear: both;
        }
        .col-half { 
            width: 48%; 
            float: left; 
        }
        .col-right {
            float: right;
        }
        .box { 
            background: #fdfdfd; 
            padding: 15px; 
            border: 1px solid #eee;
            border-radius: 5px; 
        }
        .box h3 { 
            margin-top: 0; 
            color: #5C352C; 
            font-size: 16px; 
            border-bottom: 1px solid #eee; 
            padding-bottom: 8px; 
            text-transform: uppercase;
        }
        .box p {
            margin: 5px 0;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th { 
            background-color: #5C352C; 
            color: white; 
            padding: 12px 10px; 
            text-align: left; 
            font-weight: bold;
        }
        td { 
            padding: 10px; 
            border-bottom: 1px solid #eee; 
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .total-row td { 
            border-top: 2px solid #5C352C; 
            font-size: 16px; 
            padding-top: 15px;
        }
        .footer {
            margin-top: 50px; 
            text-align: center; 
            color: #777; 
            font-size: 12px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>

    <div class="header">
        <?php 
            $logoPath = public_path('images/logo.png');
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                echo '<img src="data:image/png;base64,' . $logoData . '" class="logo" alt="U-Connect Logo">';
            }
        ?>
        <h1>Order Invoice</h1>
        <p>Order Number: <strong>{{ $order->order_number }}</strong></p>
        <p>Date: {{ $order->created_at->format('F j, Y, g:i a') }}</p>
        <p>Status: <strong style="text-transform: uppercase; color: #5C352C;">{{ $order->status }}</strong></p>
    </div>

    <div class="row clearfix">
        <div class="col-half box">
            <h3>Buyer Information</h3>
            <p><strong>Name:</strong> {{ $order->buyer->name }}</p>
            <p><strong>Email:</strong> {{ $order->buyer->email }}</p>
            <p><strong>Phone:</strong> {{ $order->buyer->phone ?? 'Not provided' }}</p>
        </div>
        <div class="col-half box col-right">
            <h3>Delivery Details</h3>
            <p><strong>Address:</strong><br>{{ nl2br(e($order->delivery_address)) }}</p>
            @if($order->notes)
                <p><strong>Order Notes:</strong><br>{{ nl2br(e($order->notes)) }}</p>
            @endif
        </div>
    </div>

    <div class="row clearfix" style="margin-bottom: 10px;">
        <div class="col-half">
            <h3 style="color: #5C352C; margin-bottom: 5px;">Order Summary</h3>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50%">Item Description</th>
                <th width="15%" class="text-center">Price (Tsh)</th>
                <th width="15%" class="text-center">Quantity</th>
                <th width="20%" class="text-right">Subtotal (Tsh)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td><strong>{{ $item->product_name }}</strong></td>
                <td class="text-center">{{ number_format($item->product_price) }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-right">{{ number_format($item->subtotal) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" class="text-right font-bold">Subtotal:</td>
                <td class="text-right">Tsh {{ number_format($order->subtotal) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="text-right font-bold">Delivery Fee:</td>
                <td class="text-right">Calculated at delivery</td>
            </tr>
            <tr class="total-row">
                <td colspan="3" class="text-right font-bold" style="color: #5C352C; font-size: 18px;">Total Expected:</td>
                <td class="text-right font-bold" style="color: #5C352C; font-size: 18px;">Tsh {{ number_format($order->total) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="row clearfix" style="margin-top: 30px;">
        <div class="col-half box" style="width: 100%;">
            <h3>Payment Information</h3>
            <p><strong>Method:</strong> {{ str_replace('_', ' ', ucwords($order->payment_method)) }}</p>
            <p><strong>Status:</strong> {{ ucfirst($order->payment_status) }}</p>
        </div>
    </div>

    <div class="footer">
        <p>If you have any questions about this order, please contact the U-Connect support team.</p>
        <p><strong>Thank you for doing business through U-Connect!</strong></p>
    </div>

</body>
</html>
