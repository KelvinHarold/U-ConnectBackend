<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'address',
        'university_id',
        'profile_photo',      
        'store_name',         
        'store_description', 
        'store_logo',         
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'profile_photo_url',
        'store_logo_url',
        'role',
        'average_rating',
        'ratings_count'
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function ordersAsBuyer()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function ordersAsSeller()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function cart()
    {
        return $this->hasMany(Cart::class, 'buyer_id');
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'seller_id');
    }

    public function sellerRatings()
    {
        return $this->hasMany(SellerRating::class, 'seller_id');
    }


 public function notifications()
{
    return $this->morphMany(Notification::class, 'notifiable')->latest();
}

public function unreadNotifications()
{
    return $this->morphMany(Notification::class, 'notifiable')
        ->whereNull('read_at')
        ->latest();
}

public function readNotifications()
{
    return $this->morphMany(Notification::class, 'notifiable')
        ->whereNotNull('read_at')
        ->latest();
}

    // Accessor for profile photo URL
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo) {
            return asset('storage/' . $this->profile_photo);
        }
        return null;
    }

    // Accessor for store logo URL
    public function getStoreLogoUrlAttribute()
    {
        if ($this->store_logo) {
            return asset('storage/' . $this->store_logo);
        }
        return null;
    }

    // Accessor for role
    public function getRoleAttribute()
    {
        return $this->roles->first()->name ?? null;
    }

    // Accessor for average rating
    public function getAverageRatingAttribute()
    {
        if ($this->isSeller() || $this->role === 'seller') {
            return (float) number_format($this->sellerRatings()->avg('rating') ?? 5.0, 1);
        }
        return null;
    }

    // Accessor for ratings count
    public function getRatingsCountAttribute()
    {
        if ($this->isSeller() || $this->role === 'seller') {
            return $this->sellerRatings()->count();
        }
        return 0;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSellers($query)
    {
        return $query->role('seller');
    }

    public function scopeBuyers($query)
    {
        return $query->role('buyer');
    }

    // Helper Methods
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isSeller()
    {
        return $this->hasRole('seller');
    }

    public function isBuyer()
    {
        return $this->hasRole('buyer');
    }

    public function hasActiveSubscription()
    {
        if ($this->isAdmin()) return true;
        $latest = $this->subscriptions()->latest('ends_at')->first();
        return $latest && $latest->isActive();
    }

    public function canSell()
    {
        return $this->isSeller() && $this->is_active && $this->hasActiveSubscription();
    }

    public function canBuy()
    {
        return $this->isBuyer() && $this->is_active;
    }
}