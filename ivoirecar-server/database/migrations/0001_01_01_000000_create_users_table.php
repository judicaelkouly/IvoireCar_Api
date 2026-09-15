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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('phone')->unique(); // Identifiant principal en CIV (ex: 0708091011)
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Rôles du système IvoireCar
            $table->enum('role', [
                'super_admin',      // Toi / L'équipe IvoireCar
                'company_admin',    // Manager de la compagnie (ex: Direction UTB)
                'station_manager',  // Chef de gare / Agent d'embarquement (Scan QR)
                'driver',           // Chauffeur de car
                'passenger'         // Client / Voyageur
            ])->default('passenger');

            // Relations optionnelles (Si l'utilisateur est un employé de compagnie ou chef de gare)
            $table->uuid('company_id')->nullable();
            $table->uuid('station_id')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index(); // Adapté pour UUID
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
