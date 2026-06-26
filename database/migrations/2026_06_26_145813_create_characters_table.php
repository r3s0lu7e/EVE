<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            // character_id is the EVE character id (from the SSO token's `sub`).
            $table->unsignedBigInteger('character_id')->primary();
            $table->string('name');
            $table->string('owner_hash')->nullable();

            // OAuth tokens — encrypted at rest via the model's `encrypted` casts.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();

            // Sync bookkeeping.
            $table->timestamp('last_synced_at')->nullable();
            $table->unsignedBigInteger('last_transaction_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
