<?php

namespace App\Services\CostBasis;

/**
 * Attributes EVE market fees to FIFO match rows and computes net_profit, using
 * a "per-trade actual" model: each completed trade is charged only the fees that
 * trade actually incurred — not a proportional slice of every fee on the account.
 *
 *  - Sales tax (transaction_tax) is linked EXACTLY. It posts at the instant of
 *    the sale, so we match it to matched rows by sell timestamp (the journal
 *    carries no transaction id). Tax landing in the same second is split across
 *    the rows that sold then, by sale value.
 *
 *  - Broker fee and market provider tax are charged at order *placement*, for
 *    orders that may never sell, and the journal can't link a placement to the
 *    fill it eventually produces. They are therefore ESTIMATED at configured
 *    rates against the trade's own sale value. Provider tax applies only to sales
 *    made at player-owned (Upwell) structures.
 *
 * Consequence: the summed net_profit no longer reconciles to the wallet balance
 * (broker/provider fees on still-open orders are intentionally not charged to
 * completed trades). The Wallet Activity panel remains the source of truth for
 * total ISK in/out; this engine answers "what did each sold item actually net".
 */
class FeeAllocator
{
    /** Upwell (player) structure ids start here; NPC stations are far below. */
    private const PLAYER_STRUCTURE_MIN_ID = 1_000_000_000_000;

    public function __construct(
        private float $brokerFeeRate = 0.0,
        private float $marketProviderTaxRate = 0.0,
    ) {
    }

    /**
     * @param  array<int,array<string,mixed>>  $matches      Rows from a CostBasisStrategy (mutated in place).
     * @param  array<int,array<string,mixed>>  $journalEntries Raw journal rows: ref_type, amount, date.
     * @return array<int,array<string,mixed>>  The matches with sales_tax_alloc, broker_fee_alloc, net_profit set.
     */
    public function allocate(array $matches, array $journalEntries): array
    {
        $taxByDate = $this->sumByDate($journalEntries, 'transaction_tax');

        // Matched sale value per sell timestamp, to split a second's tax across
        // the rows that sold in it.
        $valueByDate = [];
        foreach ($matches as $m) {
            if ($m['unmatched']) {
                continue;
            }
            $valueByDate[$m['sell_date']] = ($valueByDate[$m['sell_date']] ?? 0.0) + $this->saleValue($m);
        }

        foreach ($matches as $i => $m) {
            if ($m['unmatched']) {
                $matches[$i]['sales_tax_alloc'] = 0.0;
                $matches[$i]['broker_fee_alloc'] = 0.0;
                $matches[$i]['net_profit'] = 0.0;

                continue;
            }

            $value = $this->saleValue($m);
            $dateValue = $valueByDate[$m['sell_date']] ?? 0.0;

            // Exact sales tax for this row's share of its second's sales.
            $tax = $dateValue > 0 ? ($taxByDate[$m['sell_date']] ?? 0.0) * ($value / $dateValue) : 0.0;

            // Estimated placement fees on this trade's own value.
            $broker = $value * $this->brokerFeeRate;
            $provider = $this->isPlayerStructure($m['location_id']) ? $value * $this->marketProviderTaxRate : 0.0;

            // Provider tax is a tax, so it joins the tax column; the dashboard
            // shows sales_tax_alloc + broker_fee_alloc as combined fees.
            $matches[$i]['sales_tax_alloc'] = round($tax + $provider, 2);
            $matches[$i]['broker_fee_alloc'] = round($broker, 2);
            $matches[$i]['net_profit'] = round($m['gross_profit'] - $tax - $provider - $broker, 2);
        }

        return $matches;
    }

    private function saleValue(array $match): float
    {
        return $match['quantity'] * $match['sell_unit_price'];
    }

    private function isPlayerStructure(?int $locationId): bool
    {
        return $locationId !== null && $locationId >= self::PLAYER_STRUCTURE_MIN_ID;
    }

    /**
     * Sum journal amounts of one ref_type, keyed by exact timestamp string.
     *
     * @return array<string,float>
     */
    private function sumByDate(array $journalEntries, string $refType): array
    {
        $byDate = [];
        foreach ($journalEntries as $entry) {
            if (($entry['ref_type'] ?? null) !== $refType) {
                continue;
            }
            $date = $entry['date'] ?? null;
            if ($date === null) {
                continue;
            }
            $byDate[$date] = ($byDate[$date] ?? 0.0) + abs((float) ($entry['amount'] ?? 0));
        }

        return $byDate;
    }
}
