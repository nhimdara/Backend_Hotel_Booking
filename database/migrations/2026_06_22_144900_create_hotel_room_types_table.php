<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. "Overwater Sunset Villa"
            $table->string('badge')->nullable(); // "Best Value", "Editor's Pick", "Ultra Luxury"
            $table->string('size'); // "120m²"
            $table->string('bed_type'); // "King Bed"
            $table->unsignedTinyInteger('max_guests');
            $table->decimal('price_per_night', 10, 2);
            $table->json('images')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_room_types');
    }
};
