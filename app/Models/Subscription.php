<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'seller_id', 'status', 'payment_proof', 'amount', 'starts_at', 'ends_at'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
    
    public function isActive()
    {
        return $this->status === 'active' && $this->ends_at && $this->ends_at->isFuture();
    }
}
