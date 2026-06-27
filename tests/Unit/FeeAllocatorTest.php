<?php

namespace Tests\Unit;

use App\Services\CostBasis\FeeAllocator;
use App\Services\CostBasis\FifoStrategy;
use PHPUnit\Framework\TestCase;

class FeeAllocatorTest extends TestCase
{
    /** 100 units bought @5 at an NPC station, sold @8 on 2026-01-02 → gross 300. */
    private function match(int $sellLocation = 60003760): array
    {
        return (new FifoStrategy)->match([
            ['transaction_id' => 1, 'date' => '2026-01-01 00:00:00', 'type_id' => 34, 'location_id' => $sellLocation, 'quantity' => 100, 'unit_price' => 5.0, 'is_buy' => true],
            ['transaction_id' => 2, 'date' => '2026-01-02 00:00:00', 'type_id' => 34, 'location_id' => $sellLocation, 'quantity' => 100, 'unit_price' => 8.0, 'is_buy' => false],
        ]);
    }

    public function test_sales_tax_is_linked_to_the_sale_by_timestamp(): void
    {
        // 3% broker rate; tax posts at the exact sell timestamp.
        $journal = [
            ['ref_type' => 'transaction_tax', 'amount' => -24.0, 'date' => '2026-01-02 00:00:00'],
            // Tax for some other sale on another day must NOT touch this trade.
            ['ref_type' => 'transaction_tax', 'amount' => -999.0, 'date' => '2026-03-01 12:00:00'],
        ];

        $matches = (new FeeAllocator(brokerFeeRate: 0.03))->allocate($this->match(), $journal);

        $this->assertCount(1, $matches);
        $this->assertEqualsWithDelta(24.0, $matches[0]['sales_tax_alloc'], 0.001);
        // broker = 800 sale value * 3% = 24.
        $this->assertEqualsWithDelta(24.0, $matches[0]['broker_fee_alloc'], 0.001);
        // net = 300 gross - 24 tax - 24 broker.
        $this->assertEqualsWithDelta(252.0, $matches[0]['net_profit'], 0.001);
    }

    public function test_unrelated_placement_fees_are_not_charged_to_completed_trades(): void
    {
        // Broker fees for other/open orders exist in the journal but must not be
        // dumped onto this trade — only its own rate-based broker is charged.
        $journal = [
            ['ref_type' => 'transaction_tax', 'amount' => -24.0, 'date' => '2026-01-02 00:00:00'],
            ['ref_type' => 'brokers_fee', 'amount' => -5_000_000.0, 'date' => '2026-01-01 09:00:00'],
        ];

        $matches = (new FeeAllocator(brokerFeeRate: 0.03))->allocate($this->match(), $journal);

        $this->assertEqualsWithDelta(24.0, $matches[0]['broker_fee_alloc'], 0.001);
        $this->assertEqualsWithDelta(252.0, $matches[0]['net_profit'], 0.001);
    }

    public function test_provider_tax_applies_only_at_player_structures(): void
    {
        $journal = [['ref_type' => 'transaction_tax', 'amount' => -24.0, 'date' => '2026-01-02 00:00:00']];

        // NPC station (default) → no provider tax.
        $npc = (new FeeAllocator(brokerFeeRate: 0.03, marketProviderTaxRate: 0.01))->allocate($this->match(), $journal);
        // tax 24 + broker 24 + provider 0.
        $this->assertEqualsWithDelta(24.0, $npc[0]['sales_tax_alloc'], 0.001);
        $this->assertEqualsWithDelta(252.0, $npc[0]['net_profit'], 0.001);

        // Player structure → provider tax = 800 * 1% = 8, added to the tax column.
        $structure = (new FeeAllocator(brokerFeeRate: 0.03, marketProviderTaxRate: 0.01))
            ->allocate($this->match(1_042_508_032_148), $journal);
        $this->assertEqualsWithDelta(32.0, $structure[0]['sales_tax_alloc'], 0.001);
        $this->assertEqualsWithDelta(244.0, $structure[0]['net_profit'], 0.001);
    }

    public function test_same_second_sales_split_their_tax_by_value(): void
    {
        // Two different sells at the identical timestamp; the second's tax splits
        // across them proportionally to sale value.
        $matches = (new FifoStrategy)->match([
            ['transaction_id' => 1, 'date' => '2026-01-01 00:00:00', 'type_id' => 34, 'location_id' => 60003760, 'quantity' => 100, 'unit_price' => 5.0, 'is_buy' => true],
            ['transaction_id' => 2, 'date' => '2026-01-01 00:00:00', 'type_id' => 35, 'location_id' => 60003760, 'quantity' => 100, 'unit_price' => 5.0, 'is_buy' => true],
            ['transaction_id' => 3, 'date' => '2026-01-02 00:00:00', 'type_id' => 34, 'location_id' => 60003760, 'quantity' => 100, 'unit_price' => 8.0, 'is_buy' => false],
            ['transaction_id' => 4, 'date' => '2026-01-02 00:00:00', 'type_id' => 35, 'location_id' => 60003760, 'quantity' => 100, 'unit_price' => 2.0, 'is_buy' => false],
        ]);

        // Sale values: 800 and 200 → total 1000. Tax 100 splits 80 / 20.
        $journal = [['ref_type' => 'transaction_tax', 'amount' => -100.0, 'date' => '2026-01-02 00:00:00']];
        $matches = (new FeeAllocator)->allocate($matches, $journal);

        $byType = collect($matches)->keyBy('type_id');
        $this->assertEqualsWithDelta(80.0, $byType[34]['sales_tax_alloc'], 0.001);
        $this->assertEqualsWithDelta(20.0, $byType[35]['sales_tax_alloc'], 0.001);
    }

    public function test_unmatched_rows_carry_no_fees(): void
    {
        $matches = (new FifoStrategy)->match([
            ['transaction_id' => 1, 'date' => '2026-01-01 00:00:00', 'type_id' => 34, 'location_id' => 60003760, 'quantity' => 10, 'unit_price' => 5.0, 'is_buy' => true],
            ['transaction_id' => 2, 'date' => '2026-01-02 00:00:00', 'type_id' => 34, 'location_id' => 60003760, 'quantity' => 50, 'unit_price' => 8.0, 'is_buy' => false],
        ]);

        $journal = [['ref_type' => 'transaction_tax', 'amount' => -10.0, 'date' => '2026-01-02 00:00:00']];
        $matches = (new FeeAllocator(brokerFeeRate: 0.03))->allocate($matches, $journal);

        $unmatched = collect($matches)->firstWhere('unmatched', true);
        $this->assertSame(0.0, (float) $unmatched['broker_fee_alloc']);
        $this->assertSame(0.0, (float) $unmatched['sales_tax_alloc']);
        $this->assertSame(0.0, (float) $unmatched['net_profit']);
    }
}
