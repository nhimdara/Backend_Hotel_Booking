<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points')->default(0)->after('role');
            $table->enum('loyalty_tier', ['standard', 'silver', 'platinum', 'diamond_elite'])->default('standard')->after('loyalty_points');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points', 'loyalty_tier']);
        });
    }
};
