<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_code')->unique(); // ex: COL-98234
            $table->foreignUuid('trip_id')->nullable()->constrained('trips');
            $table->foreignUuid('sender_id')->constrained('users');
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->foreignUuid('departure_station_id')->constrained('stations');
            $table->foreignUuid('arrival_station_id')->constrained('stations');
            $table->string('description');
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('commission_amount', 10, 2); // 10%
            $table->string('security_pin_code', 6); // Code Secret de retrait à 6 chiffres
            $table->enum('status', ['registered', 'loaded', 'in_transit', 'arrived', 'delivered'])->default('registered');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
