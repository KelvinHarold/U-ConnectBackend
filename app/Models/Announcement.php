<?php
// app/Models/Announcement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'status',
        'audience',
        'published_at',
        'created_by'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'created_at' => 'datetime'
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    public function scopeActive($query)
    {
        return $query->published()->orderBy('published_at', 'desc');
    }

    // Scope for audience filtering
    public function scopeForAudience($query, $userRole = null)
    {
        if (!$userRole) {
            return $query->where('audience', 'all');
        }
        
        return $query->where(function($q) use ($userRole) {
            $q->where('audience', 'all')
              ->orWhere('audience', $userRole);
        });
    }

    // Helper methods
    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at <= now();
    }
    
    // Check if announcement is visible to a specific user role
    public function isVisibleTo($userRole): bool
    {
        if (!$this->isPublished()) {
            return false;
        }
        
        if ($this->audience === 'all') {
            return true;
        }
        
        return $this->audience === $userRole;
    }
    
    // Get audience badge class
    public function getAudienceBadgeClass(): string
    {
        return match($this->audience) {
            'all' => 'bg-purple-50 text-purple-700',
            'buyers' => 'bg-blue-50 text-blue-700',
            'sellers' => 'bg-emerald-50 text-emerald-700',
            default => 'bg-gray-50 text-gray-700',
        };
    }
    
    // Get audience label
    public function getAudienceLabel(): string
    {
        return match($this->audience) {
            'all' => 'All Users',
            'buyers' => 'Buyers Only',
            'sellers' => 'Sellers Only',
            default => 'Unknown',
        };
    }
}