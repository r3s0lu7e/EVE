<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A searchable, sortable index of every item the capsuleer has traded, with
 * all-time realized totals per item. Each row links to its detail page.
 */
class ProductList extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $sort = 'net_profit';
    #[Url] public string $dir = 'desc';

    public function updatedSearch(): void
    {
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

    private function rows()
    {
        $sortable = ['label', 'net_profit', 'gross_profit', 'fees', 'quantity', 'revenue', 'sales', 'last_traded'];
        $sort = in_array($this->sort, $sortable, true) ? $this->sort : 'net_profit';

        return DB::table('trade_matches')
            ->leftJoin('eve_types', 'eve_types.type_id', '=', 'trade_matches.type_id')
            ->where('trade_matches.unmatched', false)
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
        ]);
    }
}
