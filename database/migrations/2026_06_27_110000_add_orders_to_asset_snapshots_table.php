<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_snapshots', function (Blueprint $table) {
            // Net-worth components held on the market rather than in the hangar:
            // sell_orders is the value of items listed for sale; escrow is the
            // ISK locked in open buy orders.
            $table->decimal('sell_orders', 20, 2)->default(0)->after('assets_value');
            $table->decimal('escrow', 20, 2)->default(0)->after('sell_orders');
        });
    }

    public function down(): void
    {
        Schema::table('asset_snapshots', function (Blueprint $table) {
            $table->dropColumn(['sell_orders', 'escrow']);
        });
    }
};
