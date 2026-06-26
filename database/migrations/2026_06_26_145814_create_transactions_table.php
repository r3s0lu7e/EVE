<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            // transaction_id is the ESI wallet transaction id (globally unique per char).
            $table->unsignedBigInteger('transaction_id')->primary();
            $table->unsignedBigInteger('character_id');
            $table->timestamp('date');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('quantity');
            $table->decimal('unit_price', 20, 2);
            $table->boolean('is_buy');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('journal_ref_id')->nullable();
            $table->timestamps();

            $table->index(['character_id', 'type_id', 'date']);
            $table->index(['character_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
