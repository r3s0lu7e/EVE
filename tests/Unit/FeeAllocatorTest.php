<?php

namespace Tests\Unit;

use App\Services\CostBasis\FeeAllocator;
use App\Services\CostBasis\FifoStrategy;
use PHPUnit\Framework\TestCase;

class FeeAllocatorTest extends TestCase
{
    private function match(): array
    {
        // 100 units bought @5, sold @8 → gross 300.
        return (new FifoStrategy)->match([
            ['transaction_id' => 1, 'date' => '2026-01-01 00:00:00', 'type_id' => 34, 'location_id' => 1, 'quantity' => 100, 'unit_price' => 5.0, 'is_buy' => true],
            ['transaction_id' => 2, 'date' => '2026-01-02 00:00:00', 'type_id' => 34, 'location_id' => 1, 'quantity' => 100, 'unit_price' => 8.0, 'is_buy' => false],
        ]);
    }

    public function test_sales_tax_linked_by_context_and_subtracted(): void
    {
        $journal = [
            ['ref_type' => 'transaction_tax', 'amount' => -24.0, 'context_id' => 2],
            ['ref_type' => 'brokers_fee', 'amount' => -16.0, 'context_id' => null],
        ];

        $matches = (new FeeAllocator)->allocate($this->match(), $journal);

        $this->assertCount(1, $matches);
        $this->assertEqualsWithDelta(24.0, $matches[0]['sales_tax_alloc'], 0.001);
        $this->assertEqualsWithDelta(16.0, $matches[0]['broker_fee_alloc'], 0.001);
        // net = 300 - 24 - 16
        $this->assertEqualsWithDelta(260.0, $matches[0]['net_profit'], 0.001);
    }

    public function test_aggregate_net_profit_is_exact_even_without_context(): void
    {
        // Tax with no context_id → fallback proportional allocation.
        $journal = [
            ['ref_type' => 'transaction_tax', 'amount' => -30.0, 'context_id' => null],
            ['ref_type' => 'brokers_fee', 'amount' => -10.0, 'context_id' => null],
        ];

        $matches = (new FeeAllocator)->allocate($this->match(), $journal);

        $totalNet = array_sum(array_column($matches, 'net_profit'));
        // Σ gross (300) − Σ tax (30) − Σ broker (10) = 260, exactly.
        $this->assertEqualsWithDelta(260.0, $totalNet, 0.001);
    }

    public function test_unmatched_rows_carry_no_fees(): void
    {
        $matches = (new FifoStrategy)->match([
            ['transaction_id' => 1, 'date' => '2026-01-01 00:00:00', 'type_id' => 34, 'location_id' => 1, 'quantity' => 10, 'unit_price' => 5.0, 'is_buy' => true],
            ['transaction_id' => 2, 'date' => '2026-01-02 00:00:00', 'type_id' => 34, 'location_id' => 1, 'quantity' => 50, 'unit_price' => 8.0, 'is_buy' => false],
        ]);

        $journal = [['ref_type' => 'brokers_fee', 'amount' => -100.0, 'context_id' => null]];
        $matches = (new FeeAllocator)->allocate($matches, $journal);

        $unmatched = collect($matches)->firstWhere('unmatched', true);
        $this->assertSame(0.0, (float) $unmatched['broker_fee_alloc']);
        $this->assertSame(0.0, (float) $unmatched['net_profit']);

        // All broker fee landed on the single matched row.
        $matched = collect($matches)->firstWhere('unmatched', false);
        $this->assertEqualsWithDelta(100.0, $matched['broker_fee_alloc'], 0.001);
    }
}
