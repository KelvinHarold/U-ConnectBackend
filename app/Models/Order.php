<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'buyer_id',
        'seller_id',
        'subtotal',
        'total',
        'status',
        'payment_method',
        'payment_status',
        'delivery_address',
        'notes',
        'delivered_at',
        'cancelled_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            $order->order_number = 'ORD-' . strtoupper(uniqid());
        });
    }

    // Relationships
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function notification()
    {
        return $this->hasOne(Notification::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeByBuyer($query, $buyerId)
    {
        return $query->where('buyer_id', $buyerId);
    }

    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    // Helper Methods
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'confirmed', 'preparing']);
    }

    public function canBeConfirmed()
    {
        return $this->status === 'pending';
    }

    public function cancel()
    {
        if (!$this->canBeCancelled()) {
            return false;
        }
        
        // Restore stock for each item
        foreach ($this->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increaseStock(
                    $item->quantity, 
                    "Order #{$this->order_number} cancelled"
                );
            }
        }
        
        $this->status = 'cancelled';
        $this->cancelled_at = now();
        $this->save();
        
        return true;
    }

    public function markAsDelivered()
    {
        $this->status = 'delivered';
        $this->payment_status = 'paid';
        $this->delivered_at = now();
        $this->save();
        
        // Update product sales count
        foreach ($this->items as $item) {
            $product = Product::find($item->product_id);
            if ($product) {
                $product->increment('sales_count', $item->quantity);
            }
        }
        
        return true;
    }

    public function getWhatsAppMessage()
    {
        $itemsList = $this->items->map(function($item) {
            return "- {$item->product_name} x{$item->quantity} = TSh " . number_format($item->subtotal, 2);
        })->implode("\n");
        
        return "🛒 *New Order #{$this->order_number}* 🛒\n\n" .
               "👤 Customer: {$this->buyer->name}\n" .
               "📞 Phone: {$this->buyer->phone}\n" .
               "📍 Address: {$this->delivery_address}\n\n" .
               "📦 Items:\n{$itemsList}\n\n" .
               "💰 Total: TSh " . number_format($this->total, 2) . "\n\n" .
               "💵 Payment: Cash on Delivery\n\n" .
               "🔗 View order: " . env('APP_URL') . "/seller/orders/{$this->id}";
    }
}