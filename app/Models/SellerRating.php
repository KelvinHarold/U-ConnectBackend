<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerRating extends Model
{
    protected $fillable = [
        'seller_id',
        'buyer_id',
        'order_id',
        'rating',
        'comment',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
