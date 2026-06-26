<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_matches', function (Blueprint $table) {
            // Default dashboard view is "All characters" with the unmatched filter
            // on, scanning a sell_date range. No character_id is bound, so the
            // existing character_id-leading composites don't apply — this does.
            $table->index(['unmatched', 'sell_date'], 'tm_unmatched_sell_date_idx');

            // Single-character view: seek to the character + unmatched, then range
            // scan sell_date.
            $table->index(['character_id', 'unmatched', 'sell_date'], 'tm_char_unmatched_sell_date_idx');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            // Wallet activity: all-characters range scan ordered by date desc.
            $table->index('date', 'je_date_idx');

            // Wallet activity for a single character: seek char, range/order by date.
            $table->index(['character_id', 'date'], 'je_char_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trade_matches', function (Blueprint $table) {
            $table->dropIndex('tm_unmatched_sell_date_idx');
            $table->dropIndex('tm_char_unmatched_sell_date_idx');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndex('je_date_idx');
            $table->dropIndex('je_char_date_idx');
        });
    }
};
