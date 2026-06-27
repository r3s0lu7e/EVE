<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            // ESI serves cached wallet data on round clock boundaries, so every
            // client polling at `wallet_expires_at` hits ESI at the same instant.
            // This is the real expiry plus a small random jitter: the moment we
            // are actually due to re-sync. It spreads load past the shared
            // boundary and lets ESI regenerate before we read (avoids a stale hit).
            $table->timestamp('wallet_next_sync_at')->nullable()->after('wallet_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('wallet_next_sync_at');
        });
    }
};
