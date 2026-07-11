<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        $firstHotelId = DB::table('hotels')->min('id');
        if ($firstHotelId) {
            DB::table('users')->where('role', 'admin')->whereNull('hotel_id')->update(['hotel_id' => $firstHotelId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_id');
        });
    }
};
