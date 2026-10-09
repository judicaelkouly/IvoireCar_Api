<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Supprimer la contrainte obsolète générée par PostgreSQL
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;');

        // Reconstruire la contrainte avec la liste exacte de votre enum
        DB::statement("
            ALTER TABLE users
            ADD CONSTRAINT users_role_check
            CHECK (role IN ('super_admin', 'company_admin', 'station_manager', 'driver', 'cashier', 'passenger'));
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;');
    }
};
