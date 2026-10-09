<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter la colonne en autorisant NULL dans un premier temps
        Schema::table('departures', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
        });

        // 2. Générer un code unique pour toutes les lignes déjà existantes en BDD
        $departures = DB::table('departures')->whereNull('code')->get();

        foreach ($departures as $departure) {
            $datePrefix = date('Ymd', strtotime($departure->created_at ?? now()));
            $randomString = strtoupper(Str::random(4));
            $code = "DEP-{$datePrefix}-{$randomString}";

            DB::table('departures')
                ->where('id', $departure->id)
                ->update(['code' => $code]);
        }

        // 3. Rendre la colonne NOT NULL et UNIQUE maintenant que tout est rempli
        Schema::table('departures', function (Blueprint $table) {
            $table->string('code')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('departures', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
