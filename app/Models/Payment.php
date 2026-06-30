<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'reference',
        'amount',
        'method',
        'status',
        'qr_code_payload',
        'hold_expires_at',
        'authorized_at',
        'points_earned',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'hold_expires_at' => 'datetime',
        'authorized_at'   => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isExpired(): bool
    {
        return $this->hold_expires_at && $this->hold_expires_at->isPast() && $this->status === 'pending';
    }
}
