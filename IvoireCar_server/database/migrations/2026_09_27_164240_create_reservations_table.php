<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // Ex: TKT-2026-A89Z2

            // Client ou agent
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Relations avec le nouveau système
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departure_station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignId('destination_station_id')->constrained('stations')->cascadeOnDelete();

            // Infos voyage et passager
            $table->date('booking_date');
            $table->string('passenger_name');
            $table->string('passenger_phone');
            $table->integer('seat_number')->nullable();

            // Prix et Paiement
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('payment_method'); // WAVE, ORANGE, MTN, MOOV, STATION
            $table->enum('payment_status', ['PENDING', 'PAID', 'FAILED'])->default('PENDING');

            // Code QR
            $table->text('qr_code_value')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
