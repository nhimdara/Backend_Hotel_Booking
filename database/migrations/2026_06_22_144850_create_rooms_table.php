<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->string('room_type'); // Deluxe King, Standard Double, Penthouse Suite...
            $table->unsignedInteger('floor');
            $table->decimal('base_rate', 10, 2);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->enum('status', ['available', 'occupied', 'cleaning', 'maintenance'])->default('available');
            $table->timestamps();

            $table->unique(['hotel_id', 'room_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
