<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The earlier upgrade marked the original administrator with this flag,
        // while authorization uses the role column to recognize super admins.
        DB::table('users')
            ->where('is_super_admin', true)
            ->update(['role' => 'super_admin']);

        // Assign any remaining hotel administrators created before hotel scoping
        // was introduced to an existing hotel.
        $firstHotelId = DB::table('hotels')->min('id');

        if ($firstHotelId) {
            DB::table('users')
                ->where('role', 'admin')
                ->whereNull('hotel_id')
                ->update(['hotel_id' => $firstHotelId]);
        }
    }

    public function down(): void
    {
        // This is a data repair and should not revoke administrator access.
    }
};
