<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_matches', function (Blueprint $table) {
            // One row per (sell, consumed buy lot) pairing produced by the FIFO engine.
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('location_id')->nullable();

            $table->unsignedBigInteger('sell_transaction_id');
            // null => cost basis unknown (buy predates the local archive).
            $table->unsignedBigInteger('buy_transaction_id')->nullable();

            $table->unsignedBigInteger('quantity');
            $table->decimal('buy_unit_cost', 20, 2)->nullable();
            $table->decimal('sell_unit_price', 20, 2);
            $table->timestamp('sell_date');

            $table->decimal('gross_profit', 20, 2)->default(0);
            $table->decimal('sales_tax_alloc', 20, 2)->default(0);
            $table->decimal('broker_fee_alloc', 20, 2)->default(0);
            $table->decimal('net_profit', 20, 2)->default(0);
            $table->boolean('unmatched')->default(false);

            $table->timestamps();

            $table->index(['character_id', 'type_id', 'sell_date']);
            $table->index(['character_id', 'sell_date']);
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_matches');
    }
};
