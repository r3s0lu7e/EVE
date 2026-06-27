<?php

namespace App\Services\CostBasis;

/**
 * Allocates wallet fees (sales tax + broker fees) onto FIFO match rows and
 * computes net_profit.
 *
 * Design tradeoff: per-trade attribution is approximate (broker fees post at
 * order placement, not per fill, and aren't broken down by item), but the
 * *aggregate* is exact — every ISK of tax and broker fee is distributed, so the
 * summed net_profit reconciles with the wallet. Sales tax is attributed to the
 * specific sale via the journal's context_id when available; otherwise both
 * fees are spread proportionally to sell value across matched rows.
 */
class FeeAllocator
{
    /**
     * @param  array<int,array<string,mixed>>  $matches      Rows from a CostBasisStrategy (mutated in place).
     * @param  array<int,array<string,mixed>>  $journalEntries Raw journal rows: ref_type, amount, context_id.
     * @return array<int,array<string,mixed>>  The matches with sales_tax_alloc, broker_fee_alloc, net_profit set.
     */
    public function allocate(array $matches, array $journalEntries): array
    {
        // Only matched rows participate in fee allocation; unmatched stay at 0.
        $matchedKeys = array_keys(array_filter($matches, fn ($m) => ! $m['unmatched']));

        $sellValue = fn ($m) => $m['quantity'] * $m['sell_unit_price'];
        $totalSellValue = array_sum(array_map(fn ($k) => $sellValue($matches[$k]), $matchedKeys));

        [$taxByTxn, $totalTax] = $this->collectSalesTax($journalEntries);
        $totalBroker = $this->sumAbs($journalEntries, 'brokers_fee');

        // Tax we could pin to a specific matched sell via context_id. The rest
        // (null context, or tax for sells we hold no match for) is the
        // "unmapped" remainder, spread proportionally below — so every ISK of
        // tax is distributed even when only some sells are context-linked.
        $mappedTax = 0.0;
        foreach ($matchedKeys as $k) {
            $mappedTax += $taxByTxn[$matches[$k]['sell_transaction_id']] ?? 0.0;
        }
        $unmappedTax = max($totalTax - $mappedTax, 0.0);

        // Per-sell quantity totals (to split a sale's tax across its lot rows).
        $sellQty = [];
        foreach ($matchedKeys as $k) {
            $id = $matches[$k]['sell_transaction_id'];
            $sellQty[$id] = ($sellQty[$id] ?? 0) + $matches[$k]['quantity'];
        }

        foreach ($matchedKeys as $k) {
            $m = $matches[$k];
            $share = $totalSellValue > 0 ? $sellValue($m) / $totalSellValue : 0.0;

            // Context-linked tax for this sell (split across its lot rows by
            // quantity) plus this row's share of the unmapped remainder.
            $sellId = $m['sell_transaction_id'];
            $sellTax = $taxByTxn[$sellId] ?? 0.0;
            $contextTax = $sellQty[$sellId] > 0 ? $sellTax * ($m['quantity'] / $sellQty[$sellId]) : 0.0;
            $tax = $contextTax + $unmappedTax * $share;

            $broker = $totalBroker * $share;

            $matches[$k]['sales_tax_alloc'] = round($tax, 2);
            $matches[$k]['broker_fee_alloc'] = round($broker, 2);
            $matches[$k]['net_profit'] = round($m['gross_profit'] - $tax - $broker, 2);
        }

        // Ensure every row has the fee/net keys set.
        foreach ($matches as $i => $m) {
            $matches[$i]['sales_tax_alloc'] = $m['sales_tax_alloc'] ?? 0.0;
            $matches[$i]['broker_fee_alloc'] = $m['broker_fee_alloc'] ?? 0.0;
            $matches[$i]['net_profit'] = $m['net_profit'] ?? 0.0;
        }

        return $matches;
    }

    /**
     * @return array{0: array<int,float>, 1: float}  [taxByTransactionId, totalTax]
     */
    private function collectSalesTax(array $journalEntries): array
    {
        $byTxn = [];
        $total = 0.0;

        foreach ($journalEntries as $entry) {
            if (($entry['ref_type'] ?? null) !== 'transaction_tax') {
                continue;
            }
            $amount = abs((float) ($entry['amount'] ?? 0));
            $total += $amount;

            $contextId = $entry['context_id'] ?? null;
            if ($contextId !== null) {
                $byTxn[(int) $contextId] = ($byTxn[(int) $contextId] ?? 0.0) + $amount;
            }
        }

        return [$byTxn, $total];
    }

    private function sumAbs(array $journalEntries, string $refType): float
    {
        $sum = 0.0;
        foreach ($journalEntries as $entry) {
            if (($entry['ref_type'] ?? null) === $refType) {
                $sum += abs((float) ($entry['amount'] ?? 0));
            }
        }

        return $sum;
    }
}
