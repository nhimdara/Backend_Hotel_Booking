<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'location',
        'country',
        'latitude',
        'longitude',
        'price_per_night',
        'star_rating',
        'review_score',
        'review_count',
        'amenities',
        'images',
        'is_active',
    ];

    protected $casts = [
        'amenities'       => 'array',
        'images'          => 'array',
        'is_active'       => 'boolean',
        'price_per_night' => 'decimal:2',
        'review_score'    => 'decimal:1',
        'latitude'        => 'decimal:7',
        'longitude'       => 'decimal:7',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->whereIn('role', ['admin', 'super_admin']);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(HotelRoomType::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'hotel_badge');
    }
}
