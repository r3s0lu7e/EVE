<?php

namespace App\Services\CostBasis;

use App\Models\Character;
use App\Models\JournalEntry;
use App\Models\TradeMatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the trade_matches table for a character from its stored transactions
 * and journal. Full recompute per character (transaction volumes are small), so
 * the result is always consistent with the latest synced data.
 */
class ProfitEngine
{
    public function __construct(
        private CostBasisStrategy $strategy,
        private FeeAllocator $fees,
    ) {
    }

    public function rebuildForCharacter(Character $character): int
    {
        $transactions = Transaction::query()
            ->where('character_id', $character->character_id)
            ->orderBy('date')
            ->orderBy('transaction_id')
            ->get(['transaction_id', 'date', 'type_id', 'location_id', 'quantity', 'unit_price', 'is_buy'])
            ->map(fn (Transaction $t) => [
                'transaction_id' => (int) $t->transaction_id,
                'date' => $t->date->toDateTimeString(),
                'type_id' => (int) $t->type_id,
                'location_id' => $t->location_id !== null ? (int) $t->location_id : null,
                'quantity' => (int) $t->quantity,
                'unit_price' => (float) $t->unit_price,
                'is_buy' => (bool) $t->is_buy,
            ])
            ->all();

        $journal = JournalEntry::query()
            ->where('character_id', $character->character_id)
            ->get(['ref_type', 'amount', 'date'])
            ->map(fn (JournalEntry $j) => [
                'ref_type' => $j->ref_type,
                'amount' => (float) $j->amount,
                // Same format as Transaction::date above, so the FeeAllocator can
                // link sales tax to a sale by exact timestamp.
                'date' => $j->date->toDateTimeString(),
            ])
            ->all();

        $matches = $this->fees->allocate($this->strategy->match($transactions), $journal);

        $now = now();
        $rows = array_map(fn ($m) => [
            'character_id' => $character->character_id,
            'type_id' => $m['type_id'],
            'location_id' => $m['location_id'],
            'sell_transaction_id' => $m['sell_transaction_id'],
            'buy_transaction_id' => $m['buy_transaction_id'],
            'quantity' => $m['quantity'],
            'buy_unit_cost' => $m['buy_unit_cost'],
            'sell_unit_price' => $m['sell_unit_price'],
            'sell_date' => $m['sell_date'],
            'gross_profit' => $m['gross_profit'],
            'sales_tax_alloc' => $m['sales_tax_alloc'],
            'broker_fee_alloc' => $m['broker_fee_alloc'],
            'net_profit' => $m['net_profit'],
            'unmatched' => $m['unmatched'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $matches);

        DB::transaction(function () use ($character, $rows) {
            TradeMatch::where('character_id', $character->character_id)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                TradeMatch::insert($chunk);
            }
        });

        return count($rows);
    }
}
