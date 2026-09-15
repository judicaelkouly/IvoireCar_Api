<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->nullableUuidMorphs('payable'); // Permet de lier à Ticket ou Parcel
            $table->enum('provider', ['wave', 'orange_money', 'mtn_momo', 'moov_money', 'cash']);
            $table->string('transaction_reference')->unique()->nullable();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'successful', 'failed', 'refunded'])->default('pending');
            $table->jsonb('provider_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
