@php
    $isk = fn ($v) => number_format((float) $v, 2);
@endphp

<div wire:key="dashboard" class="space-y-6">
    {{-- Not logged in --}}
    @unless($loggedIn)
        <div class="rounded-xl border border-eve-400/15 bg-space-850/70 p-8 text-center shadow-glow">
            <svg class="mx-auto h-10 w-10 text-eve-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2.5 20 7v10l-8 4.5L4 17V7l8-4.5Z" stroke="currentColor" stroke-width="1.5"/>
            </svg>
            <h2 class="mt-3 text-base font-semibold text-slate-100">Connect your capsuleer</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-400">
                Log in with your EVE character to import wallet transactions and start tracking realized profit.
            </p>
            <a href="{{ route('eve.redirect') }}"
               class="mt-4 inline-block rounded-md bg-eve-500 px-4 py-2 font-medium text-space-900 shadow-glow transition-colors hover:bg-eve-400">
                Log in with EVE
            </a>
        </div>
    @endunless

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-slate-100">Profit Overview</h1>
            <p class="mt-0.5 text-xs text-slate-500">
                Realized FIFO profit · <span class="num">{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }}</span>
                – <span class="num">{{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($syncMessage)
                <span class="hidden text-xs text-slate-400 sm:inline">{{ $syncMessage }}</span>
            @endif
            <button wire:click="syncNow" wire:loading.attr="disabled" wire:target="syncNow"
                    class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500 disabled:opacity-50 cursor-pointer">
                <svg wire:loading.remove wire:target="syncNow" class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.7L20 8M20 4v4h-4M20 12a8 8 0 0 1-13.7 5.7L4 16m0 4v-4h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <svg wire:loading wire:target="syncNow" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" stroke-opacity=".3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span wire:loading.remove wire:target="syncNow">Sync now</span>
                <span wire:loading wire:target="syncNow">Syncing…</span>
            </button>
            <button wire:click="export"
                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-700/70 px-3 py-1.5 text-sm text-slate-300 transition-colors hover:bg-slate-800 cursor-pointer">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Export CSV
            </button>
        </div>
    </div>

    {{-- ESI freshness: EVE's wallet API is cached ~1h, so this shows how current
         the data is and when ESI will next serve new entries (viewer's timezone). --}}
    @if($walletAsOf)
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500" x-data wire:key="esi-freshness">
            <svg class="h-3.5 w-3.5 text-slate-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>Wallet data as of <span class="text-slate-300" x-text="fmtLocal(@js($walletAsOf))"></span></span>
            @if($walletExpiresAt)
                <span class="text-slate-600">·</span>
                <span>
                    ESI next refresh <span class="text-slate-300" x-text="fmtLocal(@js($walletExpiresAt))"></span>
                    <span class="text-slate-600" x-text="'(' + fmtLocalRelative(@js($walletExpiresAt)) + ')'"></span>
                </span>
            @endif
            <span class="text-slate-600">·</span>
            <span class="text-slate-600" x-text="localTzName"></span>
        </div>
    @endif

    {{-- Filters --}}
    <form wire:submit="applyFilters" class="rounded-xl border border-slate-800/80 bg-space-850/60 p-4">
        @php
            $presets = [
                'today' => 'Today',
                'yesterday' => 'Yesterday',
                '7d' => 'Last 7 days',
                '30d' => 'Last 30 days',
                'month' => 'This month',
                'last_month' => 'Last month',
                'ytd' => 'Year to date',
            ];
        @endphp
        <div class="mb-3 flex flex-wrap items-center gap-1.5">
            <span class="mr-1 text-[11px] font-medium uppercase tracking-wide text-slate-500">Quick range</span>
            @foreach($presets as $key => $label)
                <button type="button" wire:click="setRange('{{ $key }}')"
                        class="rounded-md border px-2.5 py-1 text-xs font-medium transition-colors cursor-pointer {{ $this->activePreset === $key ? 'border-eve-400/60 bg-eve-500/15 text-eve-200' : 'border-slate-700/70 text-slate-400 hover:bg-slate-800 hover:text-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <label class="block text-[11px] font-medium uppercase tracking-wide text-slate-500">From
                <div wire:ignore class="mt-1" x-data="eveDatePicker('from')" wire:key="picker-from">
                    <input x-ref="input" type="text" aria-label="From date (day/month/year)">
                </div>
            </label>
            <label class="block text-[11px] font-medium uppercase tracking-wide text-slate-500">To
                <div wire:ignore class="mt-1" x-data="eveDatePicker('to')" wire:key="picker-to">
                    <input x-ref="input" type="text" aria-label="To date (day/month/year)">
                </div>
            </label>
            <label class="block text-[11px] font-medium uppercase tracking-wide text-slate-500">Item
                <div class="relative mt-1">
                    <svg class="pointer-events-none absolute left-2 top-2 h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="m20 20-3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                    <input type="text" placeholder="Search name…" wire:model="search" data-search-focus
                           class="w-full rounded-md border border-slate-700 bg-space-800 py-1.5 pl-8 pr-2.5 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                </div>
            </label>
            <label class="block text-[11px] font-medium uppercase tracking-wide text-slate-500">Region
                <select wire:model="regionId"
                        class="mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-2.5 py-1.5 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                    <option value="">All regions</option>
                    @foreach($regions as $r)
                        <option value="{{ $r->region_id }}">{{ $r->region_name ?? ('Region '.$r->region_id) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-[11px] font-medium uppercase tracking-wide text-slate-500">Character
                <select wire:model="character"
                        class="mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-2.5 py-1.5 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                    <option value="all">All characters</option>
                    @foreach($characters as $c)
                        <option value="{{ $c->character_id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-[11px] font-medium uppercase tracking-wide text-slate-500">Min net profit
                <input type="number" placeholder="any" wire:model="minProfit"
                       class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-2.5 py-1.5 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
            </label>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-slate-400">
                <input type="checkbox" wire:model="includeUnmatched"
                       class="h-4 w-4 rounded border-slate-600 bg-space-800 text-eve-500 focus:ring-eve-400 cursor-pointer">
                Include unmatched sells <span class="text-slate-600">(no known cost basis)</span>
            </label>
            <button type="submit" wire:loading.attr="disabled" wire:target="applyFilters"
                    class="inline-flex items-center gap-1.5 rounded-md bg-eve-500 px-4 py-1.5 text-sm font-medium text-space-900 shadow-glow transition-colors hover:bg-eve-400 disabled:opacity-50 cursor-pointer">
                <svg wire:loading.remove wire:target="applyFilters" class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <svg wire:loading wire:target="applyFilters" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" stroke-opacity=".3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                <span wire:loading.remove wire:target="applyFilters">Apply filters</span>
                <span wire:loading wire:target="applyFilters">Applying…</span>
            </button>
        </div>
    </form>

    {{-- Summary KPIs --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        {{-- Hero: net profit --}}
        @php $netPos = $summary['net'] >= 0; @endphp
        <div class="col-span-2 rounded-xl border border-eve-400/30 bg-space-850/70 p-4 shadow-glow xl:col-span-2">
            <div class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Net profit
            </div>
            <div class="mt-1.5 flex items-baseline gap-2">
                <span class="num text-2xl font-semibold {{ $netPos ? 'text-emerald-400' : 'text-rose-400' }}">
                    {{ $netPos ? '▲' : '▼' }} {{ $isk($summary['net']) }}
                </span>
                <span class="text-xs text-slate-500">ISK</span>
            </div>
            <div class="mt-1 text-xs text-slate-500">Margin <span class="num text-slate-300">{{ number_format($summary['margin'], 1) }}%</span></div>
        </div>

        @php
            $cards = [
                ['Gross profit', $isk($summary['gross']).' ISK', 'text-slate-100'],
                ['Fees (tax + broker)', $isk($summary['fees']).' ISK', 'text-amber-400'],
                ['Revenue', $isk($summary['revenue']).' ISK', 'text-slate-100'],
                ['Sales', number_format($summary['sales']), 'text-slate-100'],
                ['Unmatched sells', number_format($summary['unmatched']), 'text-slate-400'],
            ];
        @endphp
        @foreach($cards as [$label, $value, $color])
            <div class="rounded-xl border border-slate-800/80 bg-space-850/50 p-4">
                <div class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
                <div class="num mt-1.5 text-sm font-semibold {{ $color }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Wallet activity (raw journal: fees, taxes, income — independent of matched trades) --}}
    <div class="rounded-xl border border-slate-800/80 bg-space-850/50" x-data="{ open: null }">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 px-4 py-3">
            <div>
                <h2 class="text-sm font-medium text-slate-300">Wallet activity</h2>
                <p class="mt-0.5 text-xs text-slate-500">All journal entries in range — click a type to see each individual fee or credit. In/Out exclude market escrow (internal transfers).</p>
            </div>
            <div class="flex items-center gap-4 text-xs">
                <span class="text-slate-500">In <span class="num font-medium text-emerald-400">{{ $isk($wallet['inflow']) }}</span></span>
                <span class="text-slate-500">Out <span class="num font-medium text-rose-400">{{ $isk($wallet['outflow']) }}</span></span>
                <span class="text-slate-500">Net <span class="num font-medium {{ $wallet['net'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $isk($wallet['net']) }}</span></span>
            </div>
        </div>

        @if(empty($wallet['rows']))
            <div class="px-4 py-8 text-center text-sm text-slate-500">No wallet journal entries in this range.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-slate-400">
                        <tr class="border-b border-slate-800/80">
                            <th scope="col" class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide">Type</th>
                            <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Entries</th>
                            <th scope="col" class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($wallet['rows'] as $w)
                            <tr class="transition-colors hover:bg-eve-400/[.04]">
                                <td class="px-4 py-2.5 text-slate-200">
                                    <button type="button"
                                            @click="open = open === @js($w['ref_type']) ? null : @js($w['ref_type'])"
                                            :aria-expanded="open === @js($w['ref_type'])"
                                            class="inline-flex w-full items-center gap-2 text-left cursor-pointer">
                                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform"
                                             :class="open === @js($w['ref_type']) ? 'rotate-90' : ''"
                                             viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>{{ $w['label'] }}</span>
                                    </button>
                                </td>
                                <td class="num px-4 py-2.5 text-right text-slate-400">{{ number_format($w['entries']) }}</td>
                                <td class="num px-4 py-2.5 text-right font-medium {{ $w['total'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $isk($w['total']) }}</td>
                            </tr>
                            <tr x-show="open === @js($w['ref_type'])" x-cloak wire:key="wallet-detail-{{ $w['ref_type'] }}">
                                <td colspan="3" class="bg-space-900/40 px-4 py-3">
                                    @if($w['truncated'])
                                        <p class="mb-2 text-xs text-slate-500">Showing latest {{ count($w['items']) }} of {{ number_format($w['entries']) }} entries.</p>
                                    @endif
                                    <table class="min-w-full text-xs">
                                        <thead class="text-slate-500">
                                            <tr>
                                                <th scope="col" class="pb-2 pr-4 text-left font-semibold uppercase tracking-wide">Date</th>
                                                <th scope="col" class="pb-2 pr-4 text-right font-semibold uppercase tracking-wide">Amount</th>
                                                <th scope="col" class="pb-2 pr-4 text-left font-semibold uppercase tracking-wide">Description</th>
                                                <th scope="col" class="pb-2 text-right font-semibold uppercase tracking-wide">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-800/40">
                                            @foreach($w['items'] as $item)
                                                <tr>
                                                    <td class="num py-2 pr-4 text-slate-400" x-data x-text="fmtLocal(@js($item['date']))"></td>
                                                    <td class="num py-2 pr-4 text-right font-medium {{ $item['amount'] >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $isk($item['amount']) }}</td>
                                                    <td class="py-2 pr-4 text-slate-300">{{ $item['description'] ?: $w['label'] }}</td>
                                                    <td class="num py-2 text-right text-slate-500">{{ $item['balance'] !== null ? $isk($item['balance']) : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Charts --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2"
         x-data="profitCharts(@js($daily), @js($top))"
         wire:key="charts-{{ md5(json_encode([$daily, $top])) }}">
        <div class="rounded-xl border border-slate-800/80 bg-space-850/50 p-4">
            <div class="mb-1 text-sm font-medium text-slate-300">Net profit by day</div>
            <div x-ref="daily" wire:ignore class="min-h-[280px]"></div>
        </div>
        <div class="rounded-xl border border-slate-800/80 bg-space-850/50 p-4">
            <div class="mb-1 text-sm font-medium text-slate-300">Top products by net profit</div>
            <div x-ref="top" wire:ignore class="min-h-[280px]"></div>
        </div>
    </div>

    {{-- Group-by + table --}}
    <div class="rounded-xl border border-slate-800/80 bg-space-850/50">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 px-4 py-3">
            <div class="inline-flex rounded-lg border border-slate-800 bg-space-800/60 p-0.5" role="tablist" aria-label="Group by">
                @foreach(['product' => 'Product', 'day' => 'Day', 'location' => 'Location', 'character' => 'Character'] as $key => $label)
                    <button wire:click="setGroupBy('{{ $key }}')" role="tab" aria-selected="{{ $groupBy === $key ? 'true' : 'false' }}"
                            class="rounded-md px-3 py-1 text-xs font-medium transition-colors cursor-pointer {{ $groupBy === $key ? 'bg-eve-500 text-space-900' : 'text-slate-400 hover:text-slate-200' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <span class="text-xs text-slate-500">{{ $rows->total() }} groups</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-slate-400">
                    @php
                        $cols = [
                            'label' => [ucfirst($groupBy), 'left'],
                            'net_profit' => ['Net profit', 'right'],
                            'gross_profit' => ['Gross', 'right'],
                            'fees' => ['Fees', 'right'],
                            'quantity' => ['Qty', 'right'],
                            'revenue' => ['Revenue', 'right'],
                            'sales' => ['Sales', 'right'],
                        ];
                    @endphp
                    <tr class="border-b border-slate-800/80">
                        @foreach($cols as $field => [$label, $align])
                            <th scope="col" tabindex="0"
                                aria-sort="{{ $sort === $field ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                wire:click="sortBy('{{ $field }}')"
                                wire:keydown.enter="sortBy('{{ $field }}')"
                                wire:keydown.space.prevent="sortBy('{{ $field }}')"
                                aria-label="Sort by {{ $label }}"
                                class="cursor-pointer select-none px-4 py-2.5 text-{{ $align }} text-[11px] font-semibold uppercase tracking-wide transition-colors hover:text-slate-100">
                                <span class="inline-flex items-center gap-1 {{ $align === 'right' ? 'flex-row-reverse' : '' }}">
                                    {{ $label }}
                                    @if($sort === $field)
                                        <span class="text-eve-400">{{ $dir === 'asc' ? '▲' : '▼' }}</span>
                                    @endif
                                </span>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($rows as $row)
                        <tr wire:key="row-{{ $loop->index }}" class="transition-colors hover:bg-eve-400/[.04]">
                            <td class="px-4 py-2.5 text-slate-200">
                                @if($groupBy === 'product')
                                    <a href="{{ route('product.show', $row->group_key) }}" wire:navigate
                                       class="inline-flex items-center gap-1.5 text-eve-300 transition-colors hover:text-eve-400 hover:underline">
                                        {{ $row->label }}
                                        <svg class="h-3 w-3 text-slate-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                @else
                                    {{ $row->label }}
                                @endif
                            </td>
                            <td class="num px-4 py-2.5 text-right font-medium {{ $row->net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $isk($row->net_profit) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-300">{{ $isk($row->gross_profit) }}</td>
                            <td class="num px-4 py-2.5 text-right text-amber-400/90">{{ $isk($row->fees) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-400">{{ number_format($row->quantity) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-300">{{ $isk($row->revenue) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-400">{{ number_format($row->sales) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <svg class="mx-auto h-8 w-8 text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                <p class="mt-2 text-sm text-slate-500">No trades match these filters.</p>
                                <p class="text-xs text-slate-600">Try widening the date range or syncing your wallet.</p>
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

    {{-- Date picker: shows d/m/Y, stores Y-m-d, defers to the Apply-filters commit --}}
    <script>
        function eveDatePicker(field) {
            return {
                init() {
                    const sharedClass = 'num w-full rounded-md border border-slate-700 bg-space-800 px-2.5 py-1.5 text-sm text-slate-100 focus:border-eve-400 focus:ring-0';

                    const fp = flatpickr(this.$refs.input, {
                        dateFormat: 'Y-m-d',
                        altInput: true,
                        altFormat: 'd/m/Y',
                        altInputClass: sharedClass,
                        allowInput: true,
                        locale: { firstDayOfWeek: 1 },
                        defaultDate: this.$wire.get(field) || null,
                        // Assigning to $wire is deferred: it updates the property
                        // locally and ships with the next Livewire request (Apply).
                        onChange: (dates, str) => { this.$wire.set(field, str, false); },
                    });

                    // Preset buttons change from/to server-side, but this input is
                    // wire:ignore — keep flatpickr in sync without re-firing onChange.
                    this.$wire.$watch(field, (value) => {
                        if (value && value !== fp.input.value) {
                            fp.setDate(value, false);
                        }
                    });
                },
            };
        }
    </script>

    {{-- ApexCharts wiring --}}
    <script>
        function profitCharts(daily, top) {
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const fmt = v => new Intl.NumberFormat().format(Math.round(v)) + ' ISK';
            const base = {
                chart: { height: 280, background: 'transparent', toolbar: { show: false }, fontFamily: 'Fira Sans, sans-serif', animations: { enabled: !reduce } },
                theme: { mode: 'dark' },
                dataLabels: { enabled: false },
                grid: { borderColor: 'rgba(148,163,184,.12)', strokeDashArray: 3 },
                tooltip: { theme: 'dark', y: { formatter: fmt } },
                noData: { text: 'No data for these filters', style: { color: '#64748b' } },
            };
            return {
                dailyChart: null,
                topChart: null,
                init() {
                    this.dailyChart = new ApexCharts(this.$refs.daily, {
                        ...base,
                        chart: { ...base.chart, type: 'bar' },
                        series: [{ name: 'Net profit', data: daily }],
                        xaxis: { type: 'category', labels: { rotate: -45, style: { colors: '#64748b', fontSize: '10px' } } },
                        yaxis: { labels: { formatter: v => new Intl.NumberFormat('en', { notation: 'compact' }).format(v), style: { colors: '#64748b' } } },
                        colors: ['#38d3ee'],
                        plotOptions: { bar: { borderRadius: 2, colors: { ranges: [{ from: -1e18, to: 0, color: '#fb7185' }] } } },
                    });
                    this.dailyChart.render();

                    this.topChart = new ApexCharts(this.$refs.top, {
                        ...base,
                        chart: { ...base.chart, type: 'bar' },
                        plotOptions: { bar: { horizontal: true, borderRadius: 2 } },
                        series: [{ name: 'Net profit', data: top.map(d => d.net) }],
                        xaxis: { categories: top.map(d => d.label), labels: { formatter: v => new Intl.NumberFormat('en', { notation: 'compact' }).format(v), style: { colors: '#64748b' } } },
                        yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
                        colors: ['#34d399'],
                    });
                    this.topChart.render();
                },
            };
        }
    </script>
</div>
