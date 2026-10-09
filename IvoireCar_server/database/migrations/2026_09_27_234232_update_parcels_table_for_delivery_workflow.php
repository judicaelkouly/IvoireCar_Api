<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            // Rendre sender_id nullable au cas où l'utilisateur est invité
            $table->foreignId('sender_id')->nullable()->change();

            // Ajout des nouvelles colonnes
            $table->string('sender_name')->nullable()->after('sender_id');
            $table->string('sender_phone')->nullable()->after('sender_name');
            $table->decimal('estimated_value', 12, 2)->default(0)->after('description');
            $table->decimal('shipping_cost', 12, 2)->default(0)->after('estimated_value');
            $table->string('payment_method')->default('WAVE')->after('price');

            // Colonnes de sécurité pour le retrait
            $table->string('collector_name')->nullable()->after('status');
            $table->string('collector_phone')->nullable()->after('collector_name');
            $table->string('collector_id_number')->nullable()->after('collector_phone');
            $table->timestamp('collected_at')->nullable()->after('collector_id_number');
        });

        // Mise à jour de l'ENUM status sous PostgreSQL
        DB::statement("ALTER TABLE parcels DROP CONSTRAINT IF EXISTS parcels_status_check;");
        DB::statement("ALTER TABLE parcels ADD CONSTRAINT parcels_status_check CHECK (status IN ('pending', 'received_at_station', 'in_transit', 'arrived_at_destination', 'collected', 'cancelled'));");
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropColumn([
                'sender_name',
                'sender_phone',
                'estimated_value',
                'shipping_cost',
                'payment_method',
                'collector_name',
                'collector_phone',
                'collector_id_number',
                'collected_at',
            ]);
        });
    }
};
