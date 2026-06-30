<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelRoomType extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'name',
        'badge',
        'size',
        'bed_type',
        'max_guests',
        'price_per_night',
        'images',
        'description',
        'is_active',
    ];

    protected $casts = [
        'images'          => 'array',
        'is_active'       => 'boolean',
        'price_per_night' => 'decimal:2',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
