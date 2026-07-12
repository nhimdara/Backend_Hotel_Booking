<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending','awaiting_approval','confirmed','checked_in','checked_out','cancelled','denied') DEFAULT 'pending'");
            DB::statement("UPDATE bookings INNER JOIN payments ON payments.booking_id = bookings.id SET bookings.status = 'awaiting_approval' WHERE bookings.status = 'confirmed' AND payments.status = 'completed'");
            return;
        }

        // SQLite is used only by the lightweight test suite. Its enum is a
        // check constraint and altering it requires rebuilding the table (or
        // Doctrine DBAL), so no schema operation is needed for empty test DBs.
    }

    public function down(): void
    {
        DB::statement("UPDATE bookings SET status = 'confirmed' WHERE status IN ('awaiting_approval','denied')");
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bookings MODIFY status ENUM('pending','confirmed','checked_in','checked_out','cancelled') DEFAULT 'pending'");
        }
    }
};
