<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $table = 'notifications';
    
    protected $fillable = [
        'notifiable_type', 'notifiable_id', 'type', 'title', 'body',
        'data', 'actions', 'icon', 'color', 'read_at', 'delivered_at'
    ];

    protected $casts = [
        'data' => 'array',
        'actions' => 'array',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }
}