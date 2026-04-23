<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'quantity',
        'min_stock_alert',
        'image',
        'images',
        'seller_id',
        'category_id',
        'is_active',
        'is_featured',
        'views_count',
        'sales_count',
        'discount_percentage',
    ];

    protected $casts = [
        'images' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'discount_percentage' => 'integer',
    ];

    protected $appends = ['discounted_price', 'average_rating', 'ratings_count'];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            $product->slug = Str::slug($product->name) . '-' . uniqid();
        });
    }

    // Relationships
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }

    public function comments()
    {
        return $this->hasMany(ProductComment::class);
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'min_stock_alert');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Helper Methods
    public function isInStock()
    {
        return $this->quantity > 0;
    }

    public function isLowStock()
    {
        return $this->quantity <= $this->min_stock_alert;
    }

    public function decreaseStock($quantity)
    {
        $oldQuantity = $this->quantity;
        $this->quantity -= $quantity;
        $this->save();
        
        $this->logInventoryChange('order', -$quantity, $oldQuantity, "Order placed");
        
        return $this;
    }

    public function increaseStock($quantity, $reason = 'Manual adjustment')
    {
        $oldQuantity = $this->quantity;
        $this->quantity += $quantity;
        $this->save();
        
        $this->logInventoryChange('add', $quantity, $oldQuantity, $reason);
        
        return $this;
    }

    public function logInventoryChange($type, $change, $oldQuantity, $reason = null, $referenceId = null)
    {
        return InventoryLog::create([
            'product_id' => $this->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'quantity_change' => $change,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $this->quantity,
            'reason' => $reason,
            'reference_id' => $referenceId,
        ]);
    }

    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function getDiscountedPriceAttribute()
    {
        if ($this->discount_percentage > 0) {
            $discount = ($this->price * $this->discount_percentage) / 100;
            return round($this->price - $discount, 2);
        }
        return $this->price;
    }

    public function getAverageRatingAttribute()
    {
        return (float) number_format($this->comments()->avg('rating') ?? 5.0, 1);
    }

    public function getRatingsCountAttribute()
    {
        return $this->comments()->count();
    }
}