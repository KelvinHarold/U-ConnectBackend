<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IssueReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'order_id',
        'product_id',
        'type',
        'subject',
        'description',
        'evidence_image',
        'status',
        'admin_response',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    // Relationships
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Accessors
    public function getEvidenceImageUrlAttribute()
    {
        if ($this->evidence_image) {
            if (filter_var($this->evidence_image, FILTER_VALIDATE_URL)) {
                return $this->evidence_image;
            }
            return url($this->evidence_image);
        }
        return null;
    }
}
