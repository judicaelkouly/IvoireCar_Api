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
        Schema::table('buses', function (Blueprint $table) {
            // Clé étrangère vers users pour le chauffeur principal (nullable)
            $table->foreignId('user_id')
                  ->nullable()
                  ->after('company_id')
                  ->constrained('users')
                  ->nullOnDelete();

            // Statut du véhicule
            $table->enum('status', ['available', 'in_transit', 'maintenance'])
                  ->default('available')
                  ->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'status']);
        });
    }
};
