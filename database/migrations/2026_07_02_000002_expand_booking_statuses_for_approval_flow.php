<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending','awaiting_approval','confirmed','checked_in','checked_out','cancelled','denied') DEFAULT 'pending'");
        DB::statement("UPDATE bookings INNER JOIN payments ON payments.booking_id = bookings.id SET bookings.status = 'awaiting_approval' WHERE bookings.status = 'confirmed' AND payments.status = 'completed'");
    }

    public function down(): void
    {
        DB::statement("UPDATE bookings SET status = 'confirmed' WHERE status IN ('awaiting_approval','denied')");
        DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending'");
    }
};
