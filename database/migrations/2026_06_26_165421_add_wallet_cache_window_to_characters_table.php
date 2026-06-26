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
            // ESI wallet/journal cache window (UTC), captured from the response
            // Last-Modified / Expires headers so the UI can show how fresh the
            // data is and when ESI will next serve updated entries.
            $table->timestamp('wallet_as_of')->nullable()->after('last_synced_at');
            $table->timestamp('wallet_expires_at')->nullable()->after('wallet_as_of');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['wallet_as_of', 'wallet_expires_at']);
        });
    }
};
