<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL databases created before the full status list need their ENUM
        // widened. PostgreSQL/SQLite fresh installs already receive the full list
        // from the create_bookings migration.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending','awaiting_approval','confirmed','checked_in','checked_out','cancelled','denied') DEFAULT 'pending'");
        }

        $completedBookingIds = DB::table('payments')
            ->where('status', 'completed')
            ->pluck('booking_id');

        DB::table('bookings')
            ->where('status', 'confirmed')
            ->whereIn('id', $completedBookingIds)
            ->update(['status' => 'awaiting_approval']);
    }

    public function down(): void
    {
        DB::table('bookings')
            ->whereIn('status', ['awaiting_approval', 'denied'])
            ->update(['status' => 'confirmed']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending'");
        }
    }
};
