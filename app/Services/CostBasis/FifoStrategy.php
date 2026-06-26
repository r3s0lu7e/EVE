<?php

namespace App\Services\CostBasis;

/**
 * First-In-First-Out cost basis: each sale consumes the oldest unsold buy lots.
 *
 * Buys push lots onto a per-type queue. Sells drain the queue head-first,
 * emitting one match row per consumed lot. A sale with no remaining buy lots
 * (e.g. stock acquired before the local archive began) is emitted as an
 * "unmatched" row with no cost basis, rather than being counted as pure profit.
 */
class FifoStrategy implements CostBasisStrategy
{
    public function match(array $transactions): array
    {
        $matches = [];

        // Per type_id FIFO queue of open buy lots: [qty_remaining, unit_cost, buy_txn_id].
        $lots = [];

        foreach ($transactions as $txn) {
            $typeId = $txn['type_id'];

            if ($txn['is_buy']) {
                $lots[$typeId][] = [
                    'qty' => (int) $txn['quantity'],
                    'unit_cost' => (float) $txn['unit_price'],
                    'buy_transaction_id' => (int) $txn['transaction_id'],
                ];

                continue;
            }

            // Sell: consume oldest lots first.
            $remaining = (int) $txn['quantity'];

            while ($remaining > 0 && ! empty($lots[$typeId])) {
                $lot = &$lots[$typeId][0];
                $take = min($remaining, $lot['qty']);

                $matches[] = $this->row($txn, $take, $lot['unit_cost'], $lot['buy_transaction_id']);

                $lot['qty'] -= $take;
                $remaining -= $take;

                if ($lot['qty'] === 0) {
                    array_shift($lots[$typeId]);
                }
                unset($lot);
            }

            // Sold more than we have cost basis for → unmatched remainder.
            if ($remaining > 0) {
                $matches[] = $this->row($txn, $remaining, null, null);
            }
        }

        return $matches;
    }

    private function row(array $sell, int $qty, ?float $buyUnitCost, ?int $buyTxnId): array
    {
        $sellUnitPrice = (float) $sell['unit_price'];
        $unmatched = $buyTxnId === null;

        return [
            'type_id' => (int) $sell['type_id'],
            'location_id' => $sell['location_id'] !== null ? (int) $sell['location_id'] : null,
            'sell_transaction_id' => (int) $sell['transaction_id'],
            'buy_transaction_id' => $buyTxnId,
            'quantity' => $qty,
            'buy_unit_cost' => $buyUnitCost,
            'sell_unit_price' => $sellUnitPrice,
            'sell_date' => $sell['date'],
            'gross_profit' => $unmatched ? 0.0 : round(($sellUnitPrice - $buyUnitCost) * $qty, 2),
            'unmatched' => $unmatched,
        ];
    }
}
