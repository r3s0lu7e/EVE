<?php

namespace App\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A searchable, sortable index of every item the capsuleer has traded, with
 * realized totals per item over the selected period. Each row links to detail.
 */
class ProductList extends Component
{
    use WithPagination;

    /** Selectable date ranges: key => [label, days-back] (null days = all time). */
    public const PRESETS = [
        'all' => ['All time', null],
        '7d' => ['7 days', 7],
        '30d' => ['30 days', 30],
        '90d' => ['90 days', 90],
        'ytd' => ['This year', null],
    ];

    #[Url] #[Session] public string $search = '';
    #[Url] #[Session] public string $preset = 'all';
    #[Url] #[Session] public string $sort = 'net_profit';
    #[Url] #[Session] public string $dir = 'desc';

    /** Commit the (deferred) search input — runs on submit / the Search button. */
    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function setPreset(string $preset): void
    {
        $this->preset = array_key_exists($preset, self::PRESETS) ? $preset : 'all';
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sort === $field) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->dir = 'desc';
        }
        $this->resetPage();
    }

    /**
     * Inclusive [start, end] datetime bounds for the selected preset, or null
     * for "all time" (no date filter).
     *
     * @return array{0:string,1:string}|null
     */
    private function dateBounds(): ?array
    {
        $end = now()->endOfDay();

        $start = match ($this->preset) {
            '7d' => now()->subDays(7)->startOfDay(),
            '30d' => now()->subDays(30)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            'ytd' => now()->startOfYear(),
            default => null,
        };

        return $start === null ? null : [$start->toDateTimeString(), $end->toDateTimeString()];
    }

    private function rows()
    {
        $sortable = ['label', 'net_profit', 'gross_profit', 'fees', 'quantity', 'revenue', 'sales', 'avg_buy', 'avg_sell', 'last_traded'];
        $sort = in_array($this->sort, $sortable, true) ? $this->sort : 'net_profit';
        $bounds = $this->dateBounds();

        return DB::table('trade_matches')
            ->leftJoin('eve_types', 'eve_types.type_id', '=', 'trade_matches.type_id')
            ->where('trade_matches.unmatched', false)
            ->when($bounds, fn ($q) => $q->whereBetween('trade_matches.sell_date', $bounds))
            ->when($this->search !== '', fn ($q) => $q->where('eve_types.name', 'like', '%'.$this->search.'%'))
            ->groupBy('trade_matches.type_id', 'eve_types.name', 'eve_types.group_name')
            ->selectRaw('trade_matches.type_id as type_id')
            ->selectRaw("COALESCE(eve_types.name, 'Type ' || trade_matches.type_id) as label")
            ->selectRaw('eve_types.group_name as group_name')
            ->selectRaw('SUM(net_profit) as net_profit')
            ->selectRaw('SUM(gross_profit) as gross_profit')
            ->selectRaw('SUM(sales_tax_alloc + broker_fee_alloc) as fees')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(quantity * sell_unit_price) as revenue')
            ->selectRaw('SUM(quantity * sell_unit_price) / NULLIF(SUM(quantity), 0) as avg_sell')
            ->selectRaw('SUM(quantity * buy_unit_cost) / NULLIF(SUM(CASE WHEN buy_unit_cost IS NOT NULL THEN quantity ELSE 0 END), 0) as avg_buy')
            ->selectRaw('COUNT(DISTINCT sell_transaction_id) as sales')
            ->selectRaw('MAX(sell_date) as last_traded')
            ->orderBy($sort, $this->dir === 'asc' ? 'asc' : 'desc')
            ->paginate(25);
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        return view('livewire.product-list', [
            'rows' => $this->rows(),
            'rangeLabel' => self::PRESETS[$this->preset][0] ?? 'All time',
        ]);
    }
}
