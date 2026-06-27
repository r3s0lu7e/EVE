<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\EveType;
use App\Models\Location;
use App\Services\EveSyncService;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Dashboard extends Component
{
    use WithPagination;

    private const MAX_JOURNAL_ITEMS = 100;

    #[Url] #[\Livewire\Attributes\Session] public string $character = 'all';
    #[Url] #[\Livewire\Attributes\Session] public string $from = '';
    #[Url] #[\Livewire\Attributes\Session] public string $to = '';
    #[Url] #[\Livewire\Attributes\Session] public string $search = '';
    #[Url] #[\Livewire\Attributes\Session] public ?int $regionId = null;
    #[Url] #[\Livewire\Attributes\Session] public string $groupBy = 'product';
    #[Url] #[\Livewire\Attributes\Session] public bool $includeUnmatched = false;
    #[Url] #[\Livewire\Attributes\Session] public ?string $minProfit = null;
    #[Url] #[\Livewire\Attributes\Session] public string $sort = 'net_profit';
    #[Url] #[\Livewire\Attributes\Session] public string $dir = 'desc';

    public ?string $syncMessage = null;
    public bool $syncing = false;

    public function mount(): void
    {
        if ($this->from === '') {
            $this->from = now()->subDays(30)->toDateString();
        }
        if ($this->to === '') {
            $this->to = now()->toDateString();
        }
    }

    /**
     * Apply the (deferred) filter inputs. Filters bind with plain `wire:model`,
     * so their values are only committed to the server when this action runs —
     * pressing the Apply button or Enter inside the filter form.
     */
    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function setGroupBy(string $groupBy): void
    {
        $this->groupBy = $groupBy;
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
    }

    /** Run a synchronous sync for the active character (local, single-user tool). */
    public function syncNow(EveSyncService $sync): void
    {
        $character = Character::find(Session::get('active_character_id'));
        if (! $character) {
            $this->syncMessage = 'Log in with EVE first.';

            return;
        }

        try {
            $result = $sync->syncCharacter($character);
            $this->syncMessage = "Synced: +{$result['transactions']} transactions, {$result['matches']} matches updated.";
        } catch (\Throwable $e) {
            $this->syncMessage = 'Sync failed: '.$e->getMessage();
        }
    }

    /* ----------------------------------------------------------------- *
     | Querying                                                          |
     * ----------------------------------------------------------------- */

    /**
     * Inclusive [start, end] datetime bounds for the selected date range.
     *
     * Returned as datetime strings so callers compare against the raw `sell_date`
     * column directly — this keeps the predicate sargable (an index on sell_date
     * can be used), unlike whereDate() which wraps the column in DATE() and forces
     * a full scan over millions of rows.
     *
     * @return array{0:string,1:string}
     */
    private function dateBounds(): array
    {
        return [
            Carbon::parse($this->from)->startOfDay()->toDateTimeString(),
            Carbon::parse($this->to)->endOfDay()->toDateTimeString(),
        ];
    }

    /** Base filtered query over trade_matches (no grouping). */
    private function baseQuery(bool $applyUnmatchedFilter = true): QueryBuilder
    {
        [$start, $end] = $this->dateBounds();

        // Columns are qualified with trade_matches.* because grouped/chart queries
        // join eve_types/locations/characters, where type_id/location_id collide.
        return DB::table('trade_matches')
            ->when($this->character !== 'all', fn ($q) => $q->where('trade_matches.character_id', $this->character))
            ->when($applyUnmatchedFilter && ! $this->includeUnmatched, fn ($q) => $q->where('trade_matches.unmatched', false))
            ->where('trade_matches.sell_date', '>=', $start)
            ->where('trade_matches.sell_date', '<=', $end)
            ->when($this->search !== '', fn ($q) => $q->whereIn(
                'trade_matches.type_id',
                EveType::query()->where('name', 'like', '%'.$this->search.'%')->select('type_id')
            ))
            ->when($this->regionId, fn ($q) => $q->whereIn(
                'trade_matches.location_id',
                Location::query()->where('region_id', $this->regionId)->select('location_id')
            ));
    }

    /** Aggregated, grouped, paginated rows for the table. */
    private function groupedRows()
    {
        [$groupCol, $labelSelect, $joins] = $this->groupConfig();

        $query = $this->baseQuery()
            ->selectRaw("$labelSelect as label")
            // The raw group key (type_id for product grouping) so the table can
            // link each product row to its detail page.
            ->selectRaw("$groupCol as group_key")
            ->selectRaw('SUM(net_profit) as net_profit')
            ->selectRaw('SUM(gross_profit) as gross_profit')
            ->selectRaw('SUM(sales_tax_alloc + broker_fee_alloc) as fees')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(quantity * sell_unit_price) as revenue')
            ->selectRaw('COUNT(DISTINCT sell_transaction_id) as sales')
            ->groupBy(DB::raw($groupCol));

        foreach ($joins as $join) {
            $query->leftJoin($join[0], $join[1], '=', $join[2]);
        }

        if ($this->minProfit !== null && $this->minProfit !== '') {
            $query->havingRaw('SUM(net_profit) >= ?', [(float) $this->minProfit]);
        }

        $sortable = ['net_profit', 'gross_profit', 'revenue', 'quantity', 'sales', 'label'];
        $sort = in_array($this->sort, $sortable, true) ? $this->sort : 'net_profit';
        $query->orderBy($sort, $this->dir === 'asc' ? 'asc' : 'desc');

        return $query->paginate(20);
    }

    /**
     * @return array{0:string,1:string,2:array<int,array<int,string>>}
     *         [groupColumn, labelSelectExpression, leftJoins]
     */
    private function groupConfig(): array
    {
        return match ($this->groupBy) {
            'day' => ["date(sell_date)", "date(sell_date)", []],
            'location' => [
                'trade_matches.location_id',
                "COALESCE(locations.name, 'Unknown location')",
                [['locations', 'locations.location_id', 'trade_matches.location_id']],
            ],
            'character' => [
                'trade_matches.character_id',
                "COALESCE(characters.name, 'Unknown')",
                [['characters', 'characters.character_id', 'trade_matches.character_id']],
            ],
            default => [
                'trade_matches.type_id',
                "COALESCE(eve_types.name, 'Type ' || trade_matches.type_id)",
                [['eve_types', 'eve_types.type_id', 'trade_matches.type_id']],
            ],
        };
    }

    private function summary(): array
    {
        $row = $this->baseQuery()
            ->selectRaw('COALESCE(SUM(net_profit),0) as net')
            ->selectRaw('COALESCE(SUM(gross_profit),0) as gross')
            ->selectRaw('COALESCE(SUM(sales_tax_alloc + broker_fee_alloc),0) as fees')
            ->selectRaw('COALESCE(SUM(quantity * sell_unit_price),0) as revenue')
            ->selectRaw('COUNT(DISTINCT sell_transaction_id) as sales')
            ->first();

        $unmatched = $this->baseQuery(applyUnmatchedFilter: false)
            ->where('trade_matches.unmatched', true)
            ->count();

        $revenue = (float) $row->revenue;

        return [
            'net' => (float) $row->net,
            'gross' => (float) $row->gross,
            'fees' => (float) $row->fees,
            'revenue' => $revenue,
            'sales' => (int) $row->sales,
            'margin' => $revenue > 0 ? ((float) $row->net / $revenue) * 100 : 0.0,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * Aggregate raw wallet journal entries (broker fees, taxes, rewards, etc.)
     * grouped by ref_type for the active character + date range.
     *
     * This is independent of trade matching: fees paid on still-open orders and
     * non-trading income have no matched sell to attach to, so they would never
     * surface in the profit table — this panel makes every journal entry visible.
     *
     * @return array{rows:array<int,array{ref_type:string,label:string,entries:int,total:float,items:array<int,array{date:string,amount:float,balance:?float,description:?string}>,truncated:bool}>,inflow:float,outflow:float,net:float}
     */
    private function walletActivity(): array
    {
        [$start, $end] = $this->dateBounds();

        $entries = DB::table('journal_entries')
            ->when($this->character !== 'all', fn ($q) => $q->where('character_id', $this->character))
            ->where('date', '>=', $start)
            ->where('date', '<=', $end)
            ->select(['date', 'ref_type', 'amount', 'balance', 'description'])
            ->orderByDesc('date')
            ->get();

        $inflow = 0.0;
        $outflow = 0.0;
        $byType = [];

        foreach ($entries as $entry) {
            $refType = (string) $entry->ref_type;
            $amount = (float) $entry->amount;
            $amount >= 0 ? $inflow += $amount : $outflow += $amount;

            if (! isset($byType[$refType])) {
                $byType[$refType] = [
                    'ref_type' => $refType,
                    'label' => ucwords(str_replace('_', ' ', $refType)),
                    'entries' => 0,
                    'total' => 0.0,
                    'items' => [],
                ];
            }

            $byType[$refType]['entries']++;
            $byType[$refType]['total'] += $amount;

            if (count($byType[$refType]['items']) < self::MAX_JOURNAL_ITEMS) {
                $byType[$refType]['items'][] = [
                    // ISO8601 UTC so the browser can render it in the viewer's tz.
                    'date' => Carbon::parse($entry->date)->toIso8601String(),
                    'amount' => $amount,
                    'balance' => $entry->balance !== null ? (float) $entry->balance : null,
                    'description' => $entry->description,
                ];
            }
        }

        $rows = array_values(array_map(function (array $group) {
            $group['total'] = (float) $group['total'];
            $group['truncated'] = $group['entries'] > count($group['items']);

            return $group;
        }, $byType));

        usort($rows, fn (array $a, array $b) => abs($b['total']) <=> abs($a['total']));

        return [
            'rows' => $rows,
            'inflow' => $inflow,
            'outflow' => $outflow,
            'net' => $inflow + $outflow,
        ];
    }

    private function dailySeries(): array
    {
        return $this->baseQuery()
            ->selectRaw('date(sell_date) as d')
            ->selectRaw('SUM(net_profit) as net')
            ->groupBy(DB::raw('date(sell_date)'))
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => ['x' => $r->d, 'y' => round((float) $r->net, 2)])
            ->all();
    }

    private function topProducts(): array
    {
        return $this->baseQuery()
            ->leftJoin('eve_types', 'eve_types.type_id', '=', 'trade_matches.type_id')
            ->selectRaw("COALESCE(eve_types.name, 'Type ' || trade_matches.type_id) as label")
            ->selectRaw('SUM(net_profit) as net')
            ->groupBy(DB::raw('trade_matches.type_id'), DB::raw('eve_types.name'))
            ->orderByDesc('net')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'net' => round((float) $r->net, 2)])
            ->all();
    }

    /* ----------------------------------------------------------------- *
     | CSV export                                                        |
     * ----------------------------------------------------------------- */

    public function export(): StreamedResponse
    {
        $rows = $this->groupedRows()->items();
        $groupLabel = ucfirst($this->groupBy);

        return response()->streamDownload(function () use ($rows, $groupLabel) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$groupLabel, 'Net Profit', 'Gross Profit', 'Fees', 'Quantity', 'Revenue', 'Sales']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->label, $r->net_profit, $r->gross_profit, $r->fees, $r->quantity, $r->revenue, $r->sales]);
            }
            fclose($out);
        }, 'eve-profit-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /* ----------------------------------------------------------------- *
     | Render                                                            |
     * ----------------------------------------------------------------- */

    #[Layout('components.layouts.app')]
    public function render()
    {
        $characters = Character::orderBy('name')->get();
        $activeId = Session::get('active_character_id');
        $active = $activeId ? $characters->firstWhere('character_id', $activeId) : null;

        // Freshness reflects the active character, falling back to the most
        // recently synced one so the cache window shows even before "logging in"
        // in a given browser (this is a single-user local tool).
        $freshnessChar = $active ?? $characters
            ->filter(fn (Character $c) => $c->wallet_as_of !== null)
            ->sortByDesc('wallet_as_of')
            ->first();

        return view('livewire.dashboard', [
            'rows' => $this->groupedRows(),
            'summary' => $this->summary(),
            'wallet' => $this->walletActivity(),
            'daily' => $this->dailySeries(),
            'top' => $this->topProducts(),
            'characters' => $characters,
            'regions' => Location::query()->whereNotNull('region_id')
                ->select('region_id', 'region_name')->distinct()
                ->orderBy('region_name')->get(),
            'loggedIn' => (bool) $activeId,
            // UTC ISO timestamps; the view renders them in the viewer's timezone.
            'walletAsOf' => $freshnessChar?->wallet_as_of?->toIso8601String(),
            'walletExpiresAt' => $freshnessChar?->wallet_expires_at?->toIso8601String(),
        ]);
    }
}
