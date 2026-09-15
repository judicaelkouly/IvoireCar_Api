<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('registration_number')->unique(); // Immatriculation
            $table->string('model')->nullable();
            $table->integer('capacity_seats')->default(60);
            $table->jsonb('seat_layout')->nullable(); // Configuration JSON des sièges (ex: 2x2, VIP)
            $table->boolean('has_wifi')->default(false);
            $table->boolean('has_ac')->default(true);
            $table->enum('status', ['available', 'in_transit', 'maintenance'])->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

