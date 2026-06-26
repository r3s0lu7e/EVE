<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            // id is the ESI wallet journal id (globally unique).
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('character_id');
            $table->timestamp('date');
            // e.g. brokers_fee, transaction_tax, market_transaction, market_escrow ...
            $table->string('ref_type');
            $table->decimal('amount', 20, 2)->nullable();
            $table->decimal('balance', 20, 2)->nullable();
            // context links a fee/tax entry back to its market transaction or order.
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('context_id_type')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['character_id', 'ref_type', 'date']);
            $table->index('context_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
