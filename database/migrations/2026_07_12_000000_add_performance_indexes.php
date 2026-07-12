<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->index(['is_active', 'review_score'], 'hotels_active_rating_idx');
            $table->index(['is_active', 'price_per_night'], 'hotels_active_price_idx');
            $table->index(['is_active', 'star_rating'], 'hotels_active_stars_idx');
        });
        Schema::table('rooms', function (Blueprint $table) {
            $table->index(['hotel_id', 'status'], 'rooms_hotel_status_idx');
            $table->index(['hotel_id', 'room_type'], 'rooms_hotel_type_idx');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['hotel_id', 'status'], 'bookings_hotel_status_idx');
            $table->index(['hotel_id', 'check_in'], 'bookings_hotel_checkin_idx');
            $table->index(['hotel_id', 'check_out'], 'bookings_hotel_checkout_idx');
            $table->index(['user_id', 'created_at'], 'bookings_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropIndex('hotels_active_rating_idx');
            $table->dropIndex('hotels_active_price_idx');
            $table->dropIndex('hotels_active_stars_idx');
        });
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex('rooms_hotel_status_idx');
            $table->dropIndex('rooms_hotel_type_idx');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_hotel_status_idx');
            $table->dropIndex('bookings_hotel_checkin_idx');
            $table->dropIndex('bookings_hotel_checkout_idx');
            $table->dropIndex('bookings_user_created_idx');
        });
    }
};
