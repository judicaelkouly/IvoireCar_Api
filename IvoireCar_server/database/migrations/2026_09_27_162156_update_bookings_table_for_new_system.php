<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // 1. Rendre user_id et trip_id facultatifs (pour ne pas casser les anciens enregistrements)
            $table->foreignId('user_id')->nullable()->change();

            // Si la clé étrangère trip_id existe, on autorise la valeur null
            if (Schema::hasColumn('bookings', 'trip_id')) {
                $table->foreignId('trip_id')->nullable()->change();
            }

            // 2. Renommer booking_code en ticket_number si nécessaire
            if (Schema::hasColumn('bookings', 'booking_code') && !Schema::hasColumn('bookings', 'ticket_number')) {
                $table->renameColumn('booking_code', 'ticket_number');
            }

            // 3. Rendre seat_number optionnel
            if (Schema::hasColumn('bookings', 'seat_number')) {
                $table->integer('seat_number')->nullable()->change();
            }

            // 4. Ajouter les nouvelles colonnes nécessaires
            if (!Schema::hasColumn('bookings', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'route_id')) {
                $table->foreignId('route_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'schedule_template_id')) {
                $table->foreignId('schedule_template_id')->nullable()->after('route_id')->constrained()->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'departure_station_id')) {
                $table->foreignId('departure_station_id')->nullable()->after('schedule_template_id')->constrained('stations')->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'destination_station_id')) {
                $table->foreignId('destination_station_id')->nullable()->after('departure_station_id')->constrained('stations')->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'booking_date')) {
                $table->date('booking_date')->nullable()->after('destination_station_id');
            }
            if (!Schema::hasColumn('bookings', 'passenger_name')) {
                $table->string('passenger_name')->nullable()->after('booking_date');
            }
            if (!Schema::hasColumn('bookings', 'passenger_phone')) {
                $table->string('passenger_phone')->nullable()->after('passenger_name');
            }
            if (!Schema::hasColumn('bookings', 'amount')) {
                $table->decimal('amount', 10, 2)->default(0)->after('seat_number');
            }
            if (!Schema::hasColumn('bookings', 'qr_code_value')) {
                $table->text('qr_code_value')->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Code d'annulation si nécessaire
        });
    }
};
