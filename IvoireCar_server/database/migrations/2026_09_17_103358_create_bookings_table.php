<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique(); // Ex: TKT-2026-A89Z2

            // Un billet peut être créé par un passager connecté ou par un agent (nullable si invité)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Relations avec les modèles du système de lignes
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departure_station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignId('destination_station_id')->constrained('stations')->cascadeOnDelete();

            // Informations sur le voyage et le passager
            $table->date('booking_date');
            $table->string('passenger_name');
            $table->string('passenger_phone');
            $table->integer('seat_number')->nullable();

            // Tarification et Paiement
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('payment_method'); // WAVE, ORANGE, MTN, MOOV, STATION
            $table->enum('payment_status', ['PENDING', 'PAID', 'FAILED'])->default('PENDING');

            // Données du Code QR (chemin d'accès ou chaîne JSON)
            $table->text('qr_code_value')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
