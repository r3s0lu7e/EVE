<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\JournalEntry;
use App\Models\TradeMatch;
use App\Models\Transaction;
use App\Services\CostBasis\ProfitEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_persists_fifo_matches_with_per_trade_fees(): void
    {
        config(['eve.broker_fee_rate' => 0.03, 'eve.market_provider_tax_rate' => 0.0]);

        $character = Character::create([
            'character_id' => 90000001,
            'name' => 'Test Trader',
        ]);

        // Buy 100 @5, buy 100 @6, sell 150 @10.
        $this->txn(1, $character, 34, 100, 5.0, '2026-01-01 00:00:00', true);
        $this->txn(2, $character, 34, 100, 6.0, '2026-01-02 00:00:00', true);
        $this->txn(3, $character, 34, 150, 10.0, '2026-01-03 00:00:00', false);

        // 75 sales tax posted at the sell instant (linked by timestamp). The
        // broker fee is a placement cost in the journal — it must NOT be counted;
        // broker is estimated per trade at the configured rate instead.
        JournalEntry::create(['id' => 1001, 'character_id' => $character->character_id, 'date' => '2026-01-03 00:00:00', 'ref_type' => 'transaction_tax', 'amount' => -75.0]);
        JournalEntry::create(['id' => 1002, 'character_id' => $character->character_id, 'date' => '2026-01-01 00:00:00', 'ref_type' => 'brokers_fee', 'amount' => -40.0]);

        $count = app(ProfitEngine::class)->rebuildForCharacter($character);

        // 100 from lot@5 + 50 from lot@6 = two match rows.
        $this->assertSame(2, $count);
        $this->assertSame(2, TradeMatch::where('character_id', $character->character_id)->count());

        // Gross = (10-5)*100 + (10-6)*50 = 500 + 200 = 700.
        $this->assertEqualsWithDelta(700.0, (float) TradeMatch::sum('gross_profit'), 0.01);

        // Tax = exact 75 (whole sale at one timestamp).
        $this->assertEqualsWithDelta(75.0, (float) TradeMatch::sum('sales_tax_alloc'), 0.01);
        // Broker = sale value 1500 * 3% = 45 (the -40 journal entry is ignored).
        $this->assertEqualsWithDelta(45.0, (float) TradeMatch::sum('broker_fee_alloc'), 0.01);
        // Net = 700 - 75 - 45 = 580.
        $this->assertEqualsWithDelta(580.0, (float) TradeMatch::sum('net_profit'), 0.01);
    }

    public function test_rebuild_is_idempotent(): void
    {
        $character = Character::create(['character_id' => 90000002, 'name' => 'Re-runner']);
        $this->txn(1, $character, 34, 10, 5.0, '2026-01-01 00:00:00', true);
        $this->txn(2, $character, 34, 10, 9.0, '2026-01-02 00:00:00', false);

        $engine = app(ProfitEngine::class);
        $engine->rebuildForCharacter($character);
        $engine->rebuildForCharacter($character);

        $this->assertSame(1, TradeMatch::where('character_id', $character->character_id)->count());
        $this->assertEqualsWithDelta(40.0, (float) TradeMatch::sum('gross_profit'), 0.01);
    }

    private function txn(int $id, Character $c, int $typeId, int $qty, float $price, string $date, bool $isBuy): void
    {
        Transaction::create([
            'transaction_id' => $id,
            'character_id' => $c->character_id,
            'date' => $date,
            'type_id' => $typeId,
            'location_id' => 60003760,
            'quantity' => $qty,
            'unit_price' => $price,
            'is_buy' => $isBuy,
        ]);
    }
}
