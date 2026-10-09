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
        Schema::create('parcels', function (Blueprint $table) {
    $table->id();
    $table->string('tracking_code')->unique(); // Code de suivi unique[cite: 1]
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('departure_station_id')->constrained('stations');
    $table->foreignId('arrival_station_id')->constrained('stations');
    $table->string('recipient_name');
    $table->string('recipient_phone');
    $table->string('description');
    $table->decimal('weight_kg', 8, 2)->nullable();
    $table->decimal('price', 10, 2);
    $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
