<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Station-trading margin calculator. You enter a buy-order price and a sell-order
 * price; it charges EVE's fees the way the in-game market does and tells you
 * whether the flip nets a profit.
 *
 * EVE market fees (verified against CCP / EVE University):
 *  - Broker's fee: charged when you PLACE an order — on BOTH the buy and the sell
 *    side. It depends on the location's standings/skills (and at player-owned
 *    Upwell structures the owner sets the rate), so the buy and sell sides can
 *    differ — hence separate inputs. Base 3%, down to 1% at an NPC station.
 *  - SCC surcharge: a fixed 0.5% also charged on placing BOTH orders; cannot be
 *    reduced by anything.
 *  - Sales tax: charged ONLY when a sell order fills, paid by the seller. Base
 *    7.5%, down to 3.37% at Accounting V. Buying never pays sales tax.
 *
 * Everything is reactive: the result recomputes as you type.
 */
class Calculator extends Component
{
    #[Url] public string $buyPrice = '';
    #[Url] public string $sellPrice = '';
    #[Url] public string $quantity = '1';
    #[Url] public string $buyBrokerFee = '0';
    #[Url] public string $sellBrokerFee = '1.5';
    #[Url] public string $sccSurcharge = '0.5';
    #[Url] public string $salesTax = '3.37';

    /**
     * Full breakdown of the flip. Null until both prices are present.
     *
     * @return array<string,float>|null
     */
    #[Computed]
    public function result(): ?array
    {
        $buy = $this->num($this->buyPrice);
        $sell = $this->num($this->sellPrice);
        $qty = max(0.0, $this->num($this->quantity));

        if ($buy <= 0 || $sell <= 0 || $qty <= 0) {
            return null;
        }

        $buyBrokerRate = $this->num($this->buyBrokerFee) / 100;
        $sellBrokerRate = $this->num($this->sellBrokerFee) / 100;
        $sccRate = $this->num($this->sccSurcharge) / 100;
        $taxRate = $this->num($this->salesTax) / 100;

        // Buy side: cost of goods + broker fee + SCC surcharge, both paid to
        // place the buy order (on order value).
        $buyCost = $buy * $qty;
        $buyBroker = $buyCost * $buyBrokerRate;
        $buyScc = $buyCost * $sccRate;
        $invested = $buyCost + $buyBroker + $buyScc;

        // Sell side: gross revenue, minus the broker fee + SCC surcharge to place
        // the sell order and the sales tax charged when it fills.
        $revenue = $sell * $qty;
        $sellBroker = $revenue * $sellBrokerRate;
        $sellScc = $revenue * $sccRate;
        $tax = $revenue * $taxRate;
        $received = $revenue - $sellBroker - $sellScc - $tax;

        $profit = $received - $invested;

        // Sell unit price at which net received exactly covers the investment.
        $sellNetRate = 1 - $sellBrokerRate - $sccRate - $taxRate;
        $breakEven = $sellNetRate > 0 ? $invested / ($qty * $sellNetRate) : 0.0;

        return [
            'buy_cost' => $buyCost,
            'buy_broker' => $buyBroker,
            'buy_scc' => $buyScc,
            'invested' => $invested,
            'revenue' => $revenue,
            'sell_broker' => $sellBroker,
            'sell_scc' => $sellScc,
            'tax' => $tax,
            'total_fees' => $buyBroker + $buyScc + $sellBroker + $sellScc + $tax,
            'received' => $received,
            'profit' => $profit,
            'profit_per_unit' => $profit / $qty,
            'margin' => $invested > 0 ? ($profit / $invested) * 100 : 0.0,
            'break_even' => $breakEven,
        ];
    }

    /**
     * Parse a user-typed number, tolerating EVE's display format (space thousands
     * separators, comma decimal — e.g. "509 700,00") as well as plain input.
     */
    private function num(string $value): float
    {
        $clean = str_replace(' ', '', trim($value));

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            // Both present: comma is the thousands separator (1,234.56).
            $clean = str_replace(',', '', $clean);
        } elseif (str_contains($clean, ',')) {
            // Comma only: it's the decimal separator (1234,56).
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.calculator');
    }
}
