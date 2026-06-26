<?php

namespace Tests\Unit;

use App\Services\CostBasis\FifoStrategy;
use PHPUnit\Framework\TestCase;

class FifoStrategyTest extends TestCase
{
    private function buy(int $id, int $typeId, int $qty, float $price, string $date): array
    {
        return $this->txn($id, $typeId, $qty, $price, $date, true);
    }

    private function sell(int $id, int $typeId, int $qty, float $price, string $date): array
    {
        return $this->txn($id, $typeId, $qty, $price, $date, false);
    }

    private function txn(int $id, int $typeId, int $qty, float $price, string $date, bool $isBuy): array
    {
        return [
            'transaction_id' => $id,
            'date' => $date,
            'type_id' => $typeId,
            'location_id' => 60003760,
            'quantity' => $qty,
            'unit_price' => $price,
            'is_buy' => $isBuy,
        ];
    }

    public function test_simple_buy_then_sell_yields_expected_profit(): void
    {
        $matches = (new FifoStrategy)->match([
            $this->buy(1, 34, 100, 5.0, '2026-01-01 00:00:00'),
            $this->sell(2, 34, 100, 8.0, '2026-01-02 00:00:00'),
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame(100, $matches[0]['quantity']);
        $this->assertSame(1, $matches[0]['buy_transaction_id']);
        $this->assertFalse($matches[0]['unmatched']);
        $this->assertEqualsWithDelta(300.0, $matches[0]['gross_profit'], 0.001); // (8-5)*100
    }

    public function test_sell_consumes_multiple_buy_lots_fifo(): void
    {
        $matches = (new FifoStrategy)->match([
            $this->buy(1, 34, 60, 5.0, '2026-01-01 00:00:00'),
            $this->buy(2, 34, 60, 6.0, '2026-01-02 00:00:00'),
            $this->sell(3, 34, 100, 10.0, '2026-01-03 00:00:00'),
        ]);

        // 60 from lot@5, then 40 from lot@6.
        $this->assertCount(2, $matches);

        $this->assertSame(60, $matches[0]['quantity']);
        $this->assertSame(1, $matches[0]['buy_transaction_id']);
        $this->assertEqualsWithDelta(300.0, $matches[0]['gross_profit'], 0.001); // (10-5)*60

        $this->assertSame(40, $matches[1]['quantity']);
        $this->assertSame(2, $matches[1]['buy_transaction_id']);
        $this->assertEqualsWithDelta(160.0, $matches[1]['gross_profit'], 0.001); // (10-6)*40

        $totalProfit = array_sum(array_column($matches, 'gross_profit'));
        $this->assertEqualsWithDelta(460.0, $totalProfit, 0.001);
    }

    public function test_types_are_matched_independently(): void
    {
        $matches = (new FifoStrategy)->match([
            $this->buy(1, 34, 10, 5.0, '2026-01-01 00:00:00'),
            $this->buy(2, 35, 10, 50.0, '2026-01-01 00:00:00'),
            $this->sell(3, 35, 10, 80.0, '2026-01-02 00:00:00'),
            $this->sell(4, 34, 10, 7.0, '2026-01-03 00:00:00'),
        ]);

        $this->assertCount(2, $matches);

        $byType = collect($matches)->keyBy('type_id');
        $this->assertEqualsWithDelta(300.0, $byType[35]['gross_profit'], 0.001); // (80-50)*10
        $this->assertEqualsWithDelta(20.0, $byType[34]['gross_profit'], 0.001);  // (7-5)*10
    }

    public function test_sell_without_cost_basis_is_flagged_unmatched(): void
    {
        $matches = (new FifoStrategy)->match([
            $this->buy(1, 34, 30, 5.0, '2026-01-01 00:00:00'),
            $this->sell(2, 34, 100, 8.0, '2026-01-02 00:00:00'),
        ]);

        $this->assertCount(2, $matches);

        // 30 matched.
        $this->assertSame(30, $matches[0]['quantity']);
        $this->assertFalse($matches[0]['unmatched']);
        $this->assertEqualsWithDelta(90.0, $matches[0]['gross_profit'], 0.001); // (8-5)*30

        // 70 unmatched — no cost basis, no profit counted.
        $this->assertSame(70, $matches[1]['quantity']);
        $this->assertTrue($matches[1]['unmatched']);
        $this->assertNull($matches[1]['buy_transaction_id']);
        $this->assertNull($matches[1]['buy_unit_cost']);
        $this->assertEqualsWithDelta(0.0, $matches[1]['gross_profit'], 0.001);
    }

    public function test_partial_lot_remains_for_next_sell(): void
    {
        $matches = (new FifoStrategy)->match([
            $this->buy(1, 34, 100, 5.0, '2026-01-01 00:00:00'),
            $this->sell(2, 34, 30, 8.0, '2026-01-02 00:00:00'),
            $this->sell(3, 34, 30, 9.0, '2026-01-03 00:00:00'),
        ]);

        $this->assertCount(2, $matches);
        $this->assertEqualsWithDelta(90.0, $matches[0]['gross_profit'], 0.001);  // (8-5)*30
        $this->assertEqualsWithDelta(120.0, $matches[1]['gross_profit'], 0.001); // (9-5)*30
        // 40 units of the original lot remain unsold (no further match rows).
    }
}
