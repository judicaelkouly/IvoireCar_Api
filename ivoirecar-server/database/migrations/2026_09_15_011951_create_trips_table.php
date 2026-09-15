<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('route_id')->constrained('routes');
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles');
            $table->foreignUuid('driver_id')->nullable()->constrained('users');
            $table->dateTime('departure_time');
            $table->dateTime('actual_departure_time')->nullable();
            $table->dateTime('actual_arrival_time')->nullable();
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->enum('status', ['scheduled', 'boarding', 'in_transit', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};

