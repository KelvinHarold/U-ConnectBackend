<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'quantity_change',
        'old_quantity',
        'new_quantity',
        'reason',
        'reference_id',
    ];

    protected $casts = [
        'quantity_change' => 'integer',
        'old_quantity' => 'integer',
        'new_quantity' => 'integer',
    ];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}