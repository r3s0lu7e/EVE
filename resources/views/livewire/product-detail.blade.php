@php
    $isk = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $name = $type?->name ?? ('Type '.$typeId);
@endphp

<div wire:key="product-{{ $typeId }}" class="space-y-6">
    {{-- Back --}}
    <a href="{{ route('dashboard') }}" wire:navigate
       class="inline-flex items-center gap-1.5 text-xs text-slate-400 transition-colors hover:text-eve-400">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Back to overview
    </a>

    {{-- Header --}}
    <div class="flex flex-wrap items-start gap-4 rounded-xl border border-eve-400/15 bg-space-850/70 p-5 shadow-glow">
        <img src="https://images.evetech.net/types/{{ $typeId }}/icon?size=64" alt="{{ $name }}"
             width="64" height="64" loading="lazy"
             class="h-16 w-16 shrink-0 rounded-lg border border-slate-800 bg-space-900">
        <div class="min-w-0 flex-1">
            <h1 class="text-lg font-semibold tracking-tight text-slate-100">{{ $name }}</h1>
            <p class="mt-0.5 text-xs text-slate-500">
                @if($type?->group_name){{ $type->group_name }}@endif
                @if($type?->category_name)<span class="text-slate-600"> · </span>{{ $type->category_name }}@endif
                @if($detail && $detail['volume'] !== null)
                    <span class="text-slate-600"> · </span><span class="num">{{ number_format($detail['volume'], 2) }}</span> m³
                @endif
                <span class="text-slate-600"> · </span>Type ID <span class="num">{{ $typeId }}</span>
            </p>
            @if($detail && $detail['description'])
                <p class="mt-2 max-w-3xl text-xs leading-relaxed text-slate-400">
                    {{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($detail['description']))), 320) }}
                </p>
            @endif
        </div>
    </div>

    {{-- Live market (Jita / The Forge), pulled from ESI --}}
    <div class="rounded-xl border border-slate-800/80 bg-space-850/50">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/80 px-4 py-3">
            <h2 class="text-sm font-medium text-slate-300">Live market — Jita <span class="text-slate-600">(The Forge)</span></h2>
            <span class="text-xs text-slate-500">
                {{ number_format($snapshot['sell_orders']) }} sell · {{ number_format($snapshot['buy_orders']) }} buy orders
            </span>
        </div>
        <div class="grid grid-cols-2 gap-px bg-slate-800/60 sm:grid-cols-4">
            @php
                $marketCards = [
                    ['Best sell', $snapshot['sell_min'] !== null ? $isk($snapshot['sell_min']).' ISK' : 'No orders', 'text-rose-300'],
                    ['Best buy', $snapshot['buy_max'] !== null ? $isk($snapshot['buy_max']).' ISK' : 'No orders', 'text-emerald-300'],
                    ['Spread', $snapshot['spread_pct'] !== null ? number_format($snapshot['spread_pct'], 1).'%' : '—', 'text-slate-100'],
                    ['On market', number_format($snapshot['sell_volume']).' / '.number_format($snapshot['buy_volume']), 'text-slate-300'],
                ];
            @endphp
            @foreach($marketCards as [$label, $value, $color])
                <div class="bg-space-850/50 p-4">
                    <div class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
                    <div class="num mt-1.5 text-sm font-semibold {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        @if($stats['avg_sell'] !== null && $snapshot['sell_min'] !== null)
            <div class="border-t border-slate-800/80 px-4 py-2.5 text-xs text-slate-500">
                Your avg sell was <span class="num text-slate-300">{{ $isk($stats['avg_sell']) }}</span> ISK ·
                Jita sell is now <span class="num text-slate-300">{{ $isk($snapshot['sell_min']) }}</span> ISK
                @php $delta = $snapshot['sell_min'] - $stats['avg_sell']; @endphp
                <span class="{{ $delta >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                    ({{ $delta >= 0 ? '+' : '' }}{{ number_format($stats['avg_sell'] != 0 ? $delta / $stats['avg_sell'] * 100 : 0, 1) }}%)
                </span>
            </div>
        @endif
    </div>

    {{-- Your realized trading for this item --}}
    <div>
        <h2 class="mb-2 text-sm font-medium text-slate-300">Your realized trading</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @php $netPos = $stats['net'] >= 0; @endphp
            <div class="col-span-2 rounded-xl border border-eve-400/30 bg-space-850/70 p-4 shadow-glow">
                <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">Net profit</div>
                <div class="mt-1.5 flex items-baseline gap-2">
                    <span class="num text-2xl font-semibold {{ $netPos ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $netPos ? '▲' : '▼' }} {{ $isk($stats['net']) }}
                    </span>
                    <span class="text-xs text-slate-500">ISK</span>
                </div>
                <div class="mt-1 text-xs text-slate-500">Margin <span class="num text-slate-300">{{ number_format($stats['margin'], 1) }}%</span></div>
            </div>
            @php
                $cards = [
                    ['Gross profit', $isk($stats['gross']).' ISK', 'text-slate-100'],
                    ['Fees', $isk($stats['fees']).' ISK', 'text-amber-400'],
                    ['Units sold', number_format($stats['qty']), 'text-slate-100'],
                    ['Avg buy', $isk($stats['avg_buy']).' ISK', 'text-slate-300'],
                    ['Avg sell', $isk($stats['avg_sell']).' ISK', 'text-slate-300'],
                ];
            @endphp
            @foreach($cards as [$label, $value, $color])
                <div class="rounded-xl border border-slate-800/80 bg-space-850/50 p-4">
                    <div class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
                    <div class="num mt-1.5 text-sm font-semibold {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Price & volume history (ESI daily market history) --}}
    <div class="rounded-xl border border-slate-800/80 bg-space-850/50 p-4">
        <div class="mb-1 text-sm font-medium text-slate-300">Price &amp; volume — last {{ count($history) }} days <span class="text-slate-600">(Jita)</span></div>
        @if(empty($history))
            <div class="py-10 text-center text-sm text-slate-500">No market history available for this item.</div>
        @else
            <div x-data="productCharts(@js($history))" wire:key="hist-{{ $typeId }}-{{ count($history) }}">
                <div x-ref="chart" wire:ignore class="min-h-[300px]"></div>
            </div>
        @endif
    </div>

    {{-- Recent trades --}}
    <div class="rounded-xl border border-slate-800/80 bg-space-850/50">
        <div class="border-b border-slate-800/80 px-4 py-3">
            <h2 class="text-sm font-medium text-slate-300">Recent trades</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-slate-400">
                    <tr class="border-b border-slate-800/80">
                        <th scope="col" class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide">Date</th>
                        <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Qty</th>
                        <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Buy cost</th>
                        <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Sell price</th>
                        <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Net profit</th>
                        <th scope="col" class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide">Location</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($recent as $t)
                        <tr class="transition-colors hover:bg-eve-400/[.04]">
                            <td class="num px-4 py-2.5 text-slate-400" x-data x-text="fmtLocal(@js($t['date']))"></td>
                            <td class="num px-4 py-2.5 text-right text-slate-400">{{ number_format($t['quantity']) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-400">{{ $isk($t['buy_unit_cost']) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-300">{{ $isk($t['sell_unit_price']) }}</td>
                            <td class="num px-4 py-2.5 text-right font-medium {{ $t['net_profit'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $isk($t['net_profit']) }}</td>
                            <td class="px-4 py-2.5 text-slate-400">{{ $t['location_name'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500">No recorded trades for this item yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ApexCharts: price line + volume bars --}}
    <script>
        function productCharts(history) {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const iskFmt = v => new Intl.NumberFormat('en', { notation: 'compact' }).format(v);
            return {
                chart: null,
                init() {
                    this.chart = new ApexCharts(this.$refs.chart, {
                        chart: {
                            height: 300, background: 'transparent', toolbar: { show: false },
                            fontFamily: 'Fira Sans, sans-serif', animations: { enabled: !reduce },
                            stacked: false,
                        },
                        theme: { mode: 'dark' },
                        series: [
                            { name: 'Avg price', type: 'line', data: history.map(h => ({ x: h.date, y: h.average })) },
                            { name: 'Volume', type: 'column', data: history.map(h => ({ x: h.date, y: h.volume })) },
                        ],
                        stroke: { width: [2, 0], curve: 'smooth' },
                        colors: ['#38d3ee', 'rgba(148,163,184,.35)'],
                        dataLabels: { enabled: false },
                        grid: { borderColor: 'rgba(148,163,184,.12)', strokeDashArray: 3 },
                        xaxis: { type: 'datetime', labels: { style: { colors: '#64748b', fontSize: '10px' } } },
                        yaxis: [
                            { seriesName: 'Avg price', labels: { formatter: iskFmt, style: { colors: '#64748b' } }, title: { text: 'ISK', style: { color: '#64748b' } } },
                            { seriesName: 'Volume', opposite: true, labels: { formatter: iskFmt, style: { colors: '#64748b' } }, title: { text: 'Units', style: { color: '#64748b' } } },
                        ],
                        tooltip: { theme: 'dark', shared: true, x: { format: 'dd MMM yyyy' } },
                        legend: { labels: { colors: '#94a3b8' } },
                        noData: { text: 'No market history', style: { color: '#64748b' } },
                    });
                    this.chart.render();
                },
            };
        }
    </script>
</div>
