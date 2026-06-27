<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_snapshots', function (Blueprint $table) {
            // A point-in-time net-worth reading for a character, so the Tracker
            // can chart growth over time. Recorded hourly-ish (when the value
            // changes) and on manual refresh. Always valued at Jita sell so the
            // series stays consistent regardless of the page's basis toggle.
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->decimal('total', 20, 2);
            $table->decimal('assets_value', 20, 2);
            $table->decimal('wallet', 20, 2);
            $table->timestamp('captured_at');

            $table->index(['character_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_snapshots');
    }
};
