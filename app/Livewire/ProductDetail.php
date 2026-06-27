<?php

namespace App\Livewire;

use App\Models\EveType;
use App\Services\MarketService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detail page for a single inventory type: your realized-trade history for it
 * (from the local archive) alongside live market data pulled from ESI.
 */
class ProductDetail extends Component
{
    public int $typeId;

    public function mount(int $typeId): void
    {
        $this->typeId = $typeId;
    }

    /**
     * Realized-trade totals for this type across every character, all-time.
     */
    private function localStats(): array
    {
        $row = DB::table('trade_matches')
            ->where('type_id', $this->typeId)
            ->where('unmatched', false)
            ->selectRaw('COALESCE(SUM(net_profit),0) as net')
            ->selectRaw('COALESCE(SUM(gross_profit),0) as gross')
            ->selectRaw('COALESCE(SUM(sales_tax_alloc + broker_fee_alloc),0) as fees')
            ->selectRaw('COALESCE(SUM(quantity),0) as qty')
            ->selectRaw('COALESCE(SUM(quantity * sell_unit_price),0) as revenue')
            ->selectRaw('COUNT(DISTINCT sell_transaction_id) as sales')
            // Quantity-weighted averages so big lots count proportionally.
            ->selectRaw('SUM(buy_unit_cost * quantity) as buy_cost_sum')
            ->selectRaw('SUM(sell_unit_price * quantity) as sell_price_sum')
            // Best/worst unit prices seen across all your trades of this item.
            ->selectRaw('MIN(buy_unit_cost) as min_buy')
            ->selectRaw('MAX(buy_unit_cost) as max_buy')
            ->selectRaw('MIN(sell_unit_price) as min_sell')
            ->selectRaw('MAX(sell_unit_price) as max_sell')
            ->selectRaw('MIN(sell_date) as first_sale')
            ->selectRaw('MAX(sell_date) as last_sale')
            ->first();

        $qty = (int) $row->qty;
        $revenue = (float) $row->revenue;

        return [
            'net' => (float) $row->net,
            'gross' => (float) $row->gross,
            'fees' => (float) $row->fees,
            'qty' => $qty,
            'revenue' => $revenue,
            'sales' => (int) $row->sales,
            'avg_buy' => $qty > 0 ? (float) $row->buy_cost_sum / $qty : null,
            'avg_sell' => $qty > 0 ? (float) $row->sell_price_sum / $qty : null,
            'min_buy' => $row->min_buy !== null ? (float) $row->min_buy : null,
            'max_buy' => $row->max_buy !== null ? (float) $row->max_buy : null,
            'min_sell' => $row->min_sell !== null ? (float) $row->min_sell : null,
            'max_sell' => $row->max_sell !== null ? (float) $row->max_sell : null,
            'margin' => $revenue > 0 ? ((float) $row->net / $revenue) * 100 : 0.0,
            'first_sale' => $row->first_sale,
            'last_sale' => $row->last_sale,
        ];
    }

    /** The most recent matched sells of this type. */
    private function recentTrades(): array
    {
        return DB::table('trade_matches')
            ->leftJoin('locations', 'locations.location_id', '=', 'trade_matches.location_id')
            ->leftJoin('characters', 'characters.character_id', '=', 'trade_matches.character_id')
            ->where('trade_matches.type_id', $this->typeId)
            ->where('trade_matches.unmatched', false)
            ->orderByDesc('trade_matches.sell_date')
            ->limit(25)
            ->get([
                'trade_matches.sell_date',
                'trade_matches.quantity',
                'trade_matches.buy_unit_cost',
                'trade_matches.sell_unit_price',
                'trade_matches.net_profit',
                'characters.name as character_name',
                'locations.name as location_name',
            ])
            ->map(fn ($r) => [
                'date' => \Illuminate\Support\Carbon::parse($r->sell_date)->toIso8601String(),
                'quantity' => (int) $r->quantity,
                'buy_unit_cost' => $r->buy_unit_cost !== null ? (float) $r->buy_unit_cost : null,
                'sell_unit_price' => (float) $r->sell_unit_price,
                'net_profit' => (float) $r->net_profit,
                'character_name' => $r->character_name,
                'location_name' => $r->location_name,
            ])
            ->all();
    }

    #[Layout('components.layouts.app')]
    public function render(MarketService $market)
    {
        $type = EveType::find($this->typeId);

        return view('livewire.product-detail', [
            'type' => $type,
            'stats' => $this->localStats(),
            'recent' => $this->recentTrades(),
            'snapshot' => $market->snapshot($this->typeId),
            'history' => $market->history($this->typeId),
            'detail' => $market->typeDetail($this->typeId),
        ]);
    }
}
