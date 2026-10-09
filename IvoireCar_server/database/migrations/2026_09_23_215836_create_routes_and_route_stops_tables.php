<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Table des Lignes globales (Ex: Abidjan -> Bondoukou)
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('departure_station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignId('arrival_station_id')->constrained('stations')->cascadeOnDelete(); // Terminus
            $table->string('name'); // Ex: "Abidjan - Bondoukou"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Table des Escales & Tarifs associés à la ligne
        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete(); // Gare d'escale ou terminus
            $table->integer('stop_order'); // Ordre de l'escale (1, 2, 3...)
            $table->decimal('price', 10, 2); // Prix depuis la gare de départ initiale
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
    }
};
