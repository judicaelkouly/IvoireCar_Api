<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();

            // Gare de départ physique exacte (Ex: Gare Adjamé Renault ou Gare Yopougon Gesco)
            $table->foreignId('departure_station_id')->constrained('stations')->cascadeOnDelete();

            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('departure_time');
            $table->integer('available_seats'); // Initialisé avec la capacité du bus
            $table->enum('status', ['scheduled', 'boarding', 'in_transit', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departures');
    }
};
