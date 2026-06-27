@php
    $isk = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $bases = [
        'sell' => ['Jita sell', 'Lowest sell — replacement cost'],
        'buy' => ['Jita buy', 'Highest buy — liquidation value'],
        'split' => ['Split', 'Midpoint of buy & sell'],
    ];
@endphp

<div wire:key="assets" class="space-y-6">
    {{-- Toolbar --}}
    <div class="space-y-3">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold tracking-tight text-slate-100">Assets</h1>
                <p class="mt-0.5 text-xs text-slate-500">
                    Net-worth valuation · priced at Jita via Fuzzwork
                    @if($valuation)
                        · <span class="num">{{ number_format($valuation['items']) }}</span> items ·
                        <span class="num">{{ number_format($valuation['types']) }}</span> types
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($characters->isNotEmpty())
                    <select wire:model.live="characterId"
                            class="rounded-md border border-slate-700 bg-space-800 py-2 pl-2.5 pr-8 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                        @foreach($characters as $c)
                            <option value="{{ $c->character_id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button wire:click="refreshAssets" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-700/70 px-3 py-2 text-sm text-slate-300 transition-colors hover:bg-slate-800 cursor-pointer disabled:opacity-60">
                    <svg wire:loading.remove wire:target="refreshAssets" class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 11A8 8 0 1 0 18.4 16M20 5v6h-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <svg wire:loading wire:target="refreshAssets" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" stroke-opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    Refresh
                </button>
            </div>
        </div>

        @if($message)
            <p class="text-xs text-eve-300">{{ $message }}</p>
        @endif
    </div>

    @if(! $character)
        {{-- Not logged in --}}
        <div class="rounded-xl border border-slate-800/80 bg-space-850/50 px-6 py-16 text-center">
            <svg class="mx-auto h-9 w-9 text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 7h18v12H3zM3 10h18M8 7V5h8v2" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
            <p class="mt-3 text-sm text-slate-400">Log in with EVE to value your assets.</p>
            <a href="{{ route('eve.redirect') }}"
               class="mt-4 inline-block rounded-md bg-eve-500 px-3.5 py-1.5 text-sm font-medium text-space-900 shadow-glow transition-colors hover:bg-eve-400">
                Log in with EVE
            </a>
        </div>
    @elseif($error)
        {{-- ESI error (most often: assets scope not granted yet) --}}
        <div class="rounded-xl border border-amber-600/40 bg-amber-950/30 px-6 py-10 text-center">
            <p class="text-sm text-amber-200">Couldn't load assets from ESI.</p>
            <p class="mx-auto mt-2 max-w-lg break-words text-xs text-amber-300/70">{{ $error }}</p>
            <p class="mt-3 text-xs text-slate-400">
                If you logged in before assets were added, log out and back in to grant the
                <span class="num">esi-assets.read_assets.v1</span> scope.
            </p>
            <form method="POST" action="{{ route('eve.logout') }}" class="mt-4">
                @csrf
                <button class="rounded-md border border-slate-700/70 px-3 py-1.5 text-sm text-slate-300 transition-colors hover:bg-slate-800 cursor-pointer">
                    Log out &amp; re-grant
                </button>
            </form>
        </div>
    @else
        {{-- Net worth headline --}}
        <div class="flex flex-wrap items-end justify-between gap-4 rounded-xl border border-eve-400/15 bg-space-850/60 px-5 py-4 shadow-glow">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total net worth</p>
                <p class="num mt-1 text-3xl font-semibold text-eve-300">{{ $isk($valuation['total']) }}<span class="ml-1.5 text-base text-slate-500">ISK</span></p>
                <p class="mt-1 text-[11px] text-slate-500">
                    Assets <span class="num text-slate-300">{{ $isk($valuation['assets_value']) }}</span>
                    · Sell orders <span class="num text-slate-300">{{ $isk($valuation['sell_orders']) }}</span>
                    · Escrow <span class="num text-slate-300">{{ $isk($valuation['escrow']) }}</span>
                    · Wallet <span class="num text-slate-300">{{ $isk($valuation['wallet']) }}</span>
                </p>
                @if($valuation['unpriced'] > 0)
                    <p class="mt-1 text-[11px] text-amber-400/80">{{ number_format($valuation['unpriced']) }} type(s) have no market price and count as 0.</p>
                @endif
            </div>

            {{-- Price basis toggle --}}
            <div class="inline-flex rounded-lg border border-slate-800 bg-space-800/60 p-0.5" role="tablist" aria-label="Price basis">
                @foreach($bases as $key => [$label, $hint])
                    <button wire:click="setBasis('{{ $key }}')" role="tab" aria-selected="{{ $basis === $key ? 'true' : 'false' }}"
                            title="{{ $hint }}"
                            class="rounded-md px-3 py-1 text-xs font-medium transition-colors cursor-pointer {{ $basis === $key ? 'bg-eve-500 text-space-900' : 'text-slate-400 hover:text-slate-200' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Net-worth tracker --}}
        <div class="rounded-xl border border-slate-800/80 bg-space-850/50 p-4">
            <div class="flex items-baseline justify-between">
                <div>
                    <div class="text-sm font-medium text-slate-300">Net worth over time</div>
                    <div class="text-[11px] text-slate-500">Recorded ~hourly when it changes, and on every refresh · valued at Jita sell</div>
                </div>
                <div class="text-[11px] text-slate-500"><span class="num">{{ count($history) }}</span> readings</div>
            </div>

            @if(count($history) >= 2)
                <div x-data="trackerChart(@js($history))"
                     wire:key="tracker-{{ count($history) }}-{{ $history[array_key_last($history)]['t'] }}">
                    <div x-ref="line" wire:ignore class="mt-2 min-h-[320px]"></div>
                </div>
            @else
                <div class="mt-2 flex min-h-[180px] flex-col items-center justify-center text-center">
                    <svg class="h-8 w-8 text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 19V5M4 19h16M7 15l4-5 3 3 5-7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ count($history) === 1 ? 'First reading captured — check back later or hit Refresh to plot your growth.' : 'No readings yet — they start recording as you view this page.' }}
                    </p>
                </div>
            @endif
        </div>

        {{-- Search --}}
        <form wire:submit="applyFilters" class="flex items-center gap-2">
            <label class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="m20 20-3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                <input type="search" placeholder="Search items…" wire:model="search" data-search-focus
                       class="w-64 rounded-md border border-slate-700 bg-space-800 py-2 pl-8 pr-2.5 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
            </label>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-md bg-eve-500 px-3.5 py-2 text-sm font-medium text-space-900 shadow-glow transition-colors hover:bg-eve-400 cursor-pointer">
                Search
            </button>
        </form>

        {{-- Breakdown table --}}
        <div class="rounded-xl border border-slate-800/80 bg-space-850/50">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-400">
                        <tr class="border-b border-slate-800/80">
                            <th scope="col" class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide">Item</th>
                            <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Qty</th>
                            <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Unit ({{ $bases[$basis][0] }})</th>
                            <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($rows as $row)
                            <tr wire:key="asset-{{ $row['type_id'] }}" class="transition-colors hover:bg-eve-400/[.04]">
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('product.show', $row['type_id']) }}" wire:navigate
                                       class="group inline-flex items-center gap-2.5">
                                        <img src="https://images.evetech.net/types/{{ $row['type_id'] }}/icon?size=32" alt=""
                                             width="32" height="32" loading="lazy"
                                             class="h-8 w-8 shrink-0 rounded border border-slate-800 bg-space-900">
                                        <span class="min-w-0">
                                            <span class="block truncate text-eve-300 transition-colors group-hover:text-eve-400 group-hover:underline">{{ $row['name'] }}</span>
                                            @if($row['group_name'])
                                                <span class="block truncate text-[11px] text-slate-500">{{ $row['group_name'] }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </td>
                                <td class="num px-4 py-2.5 text-right text-slate-400">{{ number_format($row['quantity']) }}</td>
                                <td class="num px-4 py-2.5 text-right {{ $row['unit_price'] > 0 ? 'text-slate-300' : 'text-slate-600' }}">{{ $row['unit_price'] > 0 ? $isk($row['unit_price']) : '—' }}</td>
                                <td class="num px-4 py-2.5 text-right font-medium text-eve-300">{{ $isk($row['value']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center">
                                    <svg class="mx-auto h-8 w-8 text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                    <p class="mt-2 text-sm text-slate-500">{{ $search !== '' ? 'No items match your search.' : 'No assets found for this character.' }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->hasPages())
                <div class="border-t border-slate-800/80 px-4 py-3">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ApexCharts wiring --}}
    <script>
        function trackerChart(history) {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const fmt = v => new Intl.NumberFormat().format(Math.round(v)) + ' ISK';
            const at = key => history.map(p => ({ x: new Date(p.t).getTime(), y: p[key] }));
            return {
                chart: null,
                init() {
                    this.chart = new ApexCharts(this.$refs.line, {
                        chart: { type: 'area', height: 320, background: 'transparent', toolbar: { show: false }, fontFamily: 'Fira Sans, sans-serif', animations: { enabled: !reduce } },
                        theme: { mode: 'dark' },
                        series: [
                            { name: 'Total', data: at('total') },
                            { name: 'Assets', data: at('assets') },
                            { name: 'Sell orders', data: at('sell_orders') },
                            { name: 'Escrow', data: at('escrow') },
                            { name: 'Wallet', data: at('wallet') },
                        ],
                        // Total red, Assets green, Sell orders cyan, Escrow amber,
                        // Wallet blue — familiar from jEveAssets.
                        colors: ['#fb7185', '#34d399', '#38d3ee', '#fbbf24', '#60a5fa'],
                        stroke: { width: 2, curve: 'straight' },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.20, opacityTo: 0.0, shadeIntensity: 1 } },
                        dataLabels: { enabled: false },
                        markers: { size: 0, hover: { size: 4 } },
                        grid: { borderColor: 'rgba(148,163,184,.12)', strokeDashArray: 3 },
                        legend: { position: 'top', horizontalAlign: 'left', labels: { colors: '#94a3b8' } },
                        xaxis: { type: 'datetime', labels: { datetimeUTC: false, style: { colors: '#64748b', fontSize: '10px' } } },
                        yaxis: { labels: { formatter: v => new Intl.NumberFormat('en', { notation: 'compact' }).format(v), style: { colors: '#64748b' } } },
                        tooltip: { theme: 'dark', x: { format: 'dd MMM yyyy HH:mm' }, y: { formatter: fmt }, datetimeUTC: false },
                    });
                    this.chart.render();
                },
                destroy() { this.chart?.destroy(); },
            };
        }
    </script>
</div>
