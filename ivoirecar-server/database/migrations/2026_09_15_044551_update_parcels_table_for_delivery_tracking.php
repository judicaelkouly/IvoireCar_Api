<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            // État du colis : pending (traitement), in_transit (en cours), delivered (livré), cancelled
            $table->string('status')->default('pending')->change();

            // Informations sur la personne qui retire le colis en gare d'arrivée
            $table->string('receiver_identity_name')->nullable(); // Nom de la personne venue retirer
            $table->string('receiver_identity_phone')->nullable(); // Contact
            $table->string('receiver_id_card_number')->nullable(); // N° CNI / Passeport
            $table->timestamp('delivered_at')->nullable(); // Date/Heure exacte du retrait
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropColumn([
                'receiver_identity_name',
                'receiver_identity_phone',
                'receiver_id_card_number',
                'delivered_at',
            ]);
        });
    }
};
