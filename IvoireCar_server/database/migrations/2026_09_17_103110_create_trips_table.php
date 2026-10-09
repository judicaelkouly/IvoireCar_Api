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
        Schema::create('trips', function (Blueprint $table) {
        $table->id();
        $table->foreignId('company_id')->constrained()->cascadeOnDelete();
        $table->foreignId('departure_station_id')->constrained('stations')->cascadeOnDelete();
        $table->foreignId('arrival_station_id')->constrained('stations')->cascadeOnDelete();
        $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();
        $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
        $table->dateTime('departure_time');
        $table->decimal('price', 10, 2); // Prix en FCFA
        $table->enum('status', ['planned', 'in_transit', 'completed', 'cancelled'])->default('planned');
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
