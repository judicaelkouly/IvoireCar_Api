<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignUuid('departure_station_id')->constrained('stations');
            $table->foreignUuid('arrival_station_id')->constrained('stations');
            $table->decimal('price', 10, 2); 
            $table->integer('estimated_duration_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignUuid('station_id')->constrained('stations');
            $table->integer('stop_order');
            $table->decimal('price_from_departure', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('routes');
    }
};

