<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique(); // ex: IVC-2026-X89A
            $table->foreignUuid('trip_id')->constrained('trips');
            $table->foreignUuid('user_id')->constrained('users'); // Acheteur
            $table->string('passenger_name');
            $table->string('passenger_phone');
            $table->integer('seat_number');
            $table->decimal('price', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->string('qr_code_hash')->unique();
            $table->enum('status', ['pending', 'confirmed', 'boarded', 'cancelled', 'expired'])->default('pending');
            $table->timestamp('boarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

