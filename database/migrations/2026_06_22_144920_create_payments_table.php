<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['card', 'qr_scan', 'bank_transfer'])->default('qr_scan');
            $table->enum('status', ['pending', 'authorized', 'completed', 'failed', 'expired'])->default('pending');
            $table->string('qr_code_payload')->nullable(); // public QR image URL shown to the user
            $table->timestamp('hold_expires_at')->nullable(); // the "14:59 countdown"
            $table->timestamp('authorized_at')->nullable();
            $table->unsignedInteger('points_earned')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
