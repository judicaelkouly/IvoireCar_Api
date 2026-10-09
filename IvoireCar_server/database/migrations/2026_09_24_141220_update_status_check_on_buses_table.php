<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer l'ancienne contrainte et ajouter la nouvelle autorisant 'active'
        DB::statement("ALTER TABLE buses DROP CONSTRAINT IF EXISTS buses_status_check");
        DB::statement("ALTER TABLE buses ADD CONSTRAINT buses_status_check CHECK (status IN ('available', 'active', 'in_transit', 'maintenance', 'inactive', 'hors_service'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE buses DROP CONSTRAINT IF EXISTS buses_status_check");
        DB::statement("ALTER TABLE buses ADD CONSTRAINT buses_status_check CHECK (status IN ('available', 'in_transit', 'maintenance'))");
    }
};
