<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('route_id')->constrained()->onDelete('cascade');
            $table->foreignId('departure_station_id')->constrained('stations')->onDelete('cascade');
            $table->foreignId('bus_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            // Heure fixée (ex: "06:00:00")
            $table->time('departure_time');

            // Jours de la semaine sous forme de tableau JSON: [1,2,3,4,5,6,7] (1 = Lundi, 7 = Dimanche)
            $table->json('days_of_week')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Mise à jour optionnelle : Ajout de la clé étrangère dans la table departures si ce n'est pas fait
        if (Schema::hasTable('departures') && !Schema::hasColumn('departures', 'schedule_template_id')) {
            Schema::table('departures', function (Blueprint $table) {
                $table->foreignId('schedule_template_id')->nullable()->after('company_id')->constrained()->onDelete('set null');
                $table->date('departure_date')->after('schedule_template_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('departures', 'schedule_template_id')) {
            Schema::table('departures', function (Blueprint $table) {
                $table->dropForeign(['schedule_template_id']);
                $table->dropColumn(['schedule_template_id', 'departure_date']);
            });
        }
        Schema::dropIfExists('schedule_templates');
    }
};
