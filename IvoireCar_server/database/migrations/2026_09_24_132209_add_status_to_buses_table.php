<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('buses', 'status')) {
            Schema::table('buses', function (Blueprint $table) {
                $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('buses', 'status')) {
            Schema::table('buses', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
