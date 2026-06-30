<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique(); // "Top Rated", "Pool Access", "Hidden Gem", "New", "Eco-Certified Gold"
            $table->string('color')->default('gray'); // for UI theming: amber, teal, purple, green...
            $table->timestamps();
        });

        Schema::create('hotel_badge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_badge');
        Schema::dropIfExists('badges');
    }
};
