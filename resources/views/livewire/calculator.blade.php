@php
    $isk = fn ($v) => number_format((float) $v, 2);
    $r = $this->result;
    $profit = $r['profit'] ?? 0.0;
    $profitable = $profit > 0;
@endphp

<div wire:key="calculator" class="space-y-6">
    {{-- Header --}}
    <div>
        <h1 class="text-lg font-semibold tracking-tight text-slate-100">Trade Calculator</h1>
        <p class="mt-0.5 text-xs text-slate-500">
            Enter a buy-order and sell-order price; fees are charged the way the EVE market does.
            Is the flip profitable?
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[20rem_1fr]">
        {{-- Inputs --}}
        <div class="space-y-4 rounded-xl border border-slate-800/80 bg-space-850/50 p-5">
            <div>
                <label for="buyPrice" class="block text-xs font-medium text-slate-400">Buy price (per unit)</label>
                <input id="buyPrice" type="text" inputmode="decimal" wire:model.live.debounce.300ms="buyPrice"
                       placeholder="509 700"
                       class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
            </div>

            <div>
                <label for="sellPrice" class="block text-xs font-medium text-slate-400">Sell price (per unit)</label>
                <input id="sellPrice" type="text" inputmode="decimal" wire:model.live.debounce.300ms="sellPrice"
                       placeholder="824 700"
                       class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
            </div>

            <div>
                <label for="quantity" class="block text-xs font-medium text-slate-400">Quantity</label>
                <input id="quantity" type="text" inputmode="numeric" wire:model.live.debounce.300ms="quantity"
                       class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
            </div>

            <div class="space-y-3 border-t border-slate-800 pt-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Fees &amp; tax</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="buyBrokerFee" class="block text-xs font-medium text-slate-400">Buy broker fee %</label>
                        <input id="buyBrokerFee" type="text" inputmode="decimal" wire:model.live.debounce.300ms="buyBrokerFee"
                               class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                        <p class="mt-1 text-[10px] leading-tight text-slate-500">Paid to place the buy order.</p>
                    </div>
                    <div>
                        <label for="sellBrokerFee" class="block text-xs font-medium text-slate-400">Sell broker fee %</label>
                        <input id="sellBrokerFee" type="text" inputmode="decimal" wire:model.live.debounce.300ms="sellBrokerFee"
                               class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                        <p class="mt-1 text-[10px] leading-tight text-slate-500">Paid to place the sell order.</p>
                    </div>
                    <div>
                        <label for="sccSurcharge" class="block text-xs font-medium text-slate-400">SCC surcharge %</label>
                        <input id="sccSurcharge" type="text" inputmode="decimal" wire:model.live.debounce.300ms="sccSurcharge"
                               class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                        <p class="mt-1 text-[10px] leading-tight text-slate-500">Fixed 0.5% on both orders.</p>
                    </div>
                    <div>
                        <label for="salesTax" class="block text-xs font-medium text-slate-400">Sales tax %</label>
                        <input id="salesTax" type="text" inputmode="decimal" wire:model.live.debounce.300ms="salesTax"
                               class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                        <p class="mt-1 text-[10px] leading-tight text-slate-500">Sell only, when it fills.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Result --}}
        <div class="space-y-4">
            @if(! $r)
                <div class="flex h-full min-h-[12rem] items-center justify-center rounded-xl border border-slate-800/80 bg-space-850/50 px-6 py-16 text-center">
                    <p class="text-sm text-slate-500">Enter a buy and sell price to see the result.</p>
                </div>
            @else
                {{-- Verdict --}}
                <div class="rounded-xl border px-5 py-5 {{ $profitable ? 'border-emerald-600/40 bg-emerald-950/30' : 'border-rose-600/40 bg-rose-950/30' }}">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide {{ $profitable ? 'text-emerald-300' : 'text-rose-300' }}">
                                {{ $profitable ? 'Profitable' : 'Loss' }}
                            </p>
                            <p class="num mt-1 text-3xl font-semibold {{ $profitable ? 'text-emerald-200' : 'text-rose-200' }}">
                                {{ $profit >= 0 ? '+' : '' }}{{ $isk($profit) }} <span class="text-base font-normal opacity-70">ISK</span>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400">Margin</p>
                            <p class="num text-xl font-semibold {{ $profitable ? 'text-emerald-200' : 'text-rose-200' }}">
                                {{ number_format($r['margin'], 2) }}%
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-xs text-slate-400">
                        <span>Profit / unit: <span class="num text-slate-200">{{ $isk($r['profit_per_unit']) }}</span></span>
                        <span>Break-even sell price: <span class="num text-slate-200">{{ $isk($r['break_even']) }}</span></span>
                    </div>
                </div>

                {{-- Breakdown --}}
                <div class="overflow-hidden rounded-xl border border-slate-800/80 bg-space-850/50">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-800/70">
                            <tr>
                                <td class="px-5 py-2.5 text-slate-400">Buy cost</td>
                                <td class="num px-5 py-2.5 text-right text-slate-200">{{ $isk($r['buy_cost']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-2.5 pl-8 text-slate-500">+ Broker fee (buy)</td>
                                <td class="num px-5 py-2.5 text-right text-rose-300">{{ $isk($r['buy_broker']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-2.5 pl-8 text-slate-500">+ SCC surcharge (buy)</td>
                                <td class="num px-5 py-2.5 text-right text-rose-300">{{ $isk($r['buy_scc']) }}</td>
                            </tr>
                            <tr class="bg-space-800/40">
                                <td class="px-5 py-2.5 font-medium text-slate-300">Total invested</td>
                                <td class="num px-5 py-2.5 text-right font-medium text-slate-100">{{ $isk($r['invested']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-2.5 text-slate-400">Sell revenue</td>
                                <td class="num px-5 py-2.5 text-right text-slate-200">{{ $isk($r['revenue']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-2.5 pl-8 text-slate-500">− Broker fee (sell)</td>
                                <td class="num px-5 py-2.5 text-right text-rose-300">{{ $isk($r['sell_broker']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-2.5 pl-8 text-slate-500">− SCC surcharge (sell)</td>
                                <td class="num px-5 py-2.5 text-right text-rose-300">{{ $isk($r['sell_scc']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-2.5 pl-8 text-slate-500">− Sales tax</td>
                                <td class="num px-5 py-2.5 text-right text-rose-300">{{ $isk($r['tax']) }}</td>
                            </tr>
                            <tr class="bg-space-800/40">
                                <td class="px-5 py-2.5 font-medium text-slate-300">Net received</td>
                                <td class="num px-5 py-2.5 text-right font-medium text-slate-100">{{ $isk($r['received']) }}</td>
                            </tr>
                            <tr>
                                <td class="px-5 py-2.5 text-slate-400">Total fees &amp; tax</td>
                                <td class="num px-5 py-2.5 text-right text-rose-300">{{ $isk($r['total_fees']) }}</td>
                            </tr>
                            <tr class="{{ $profitable ? 'bg-emerald-950/20' : 'bg-rose-950/20' }}">
                                <td class="px-5 py-3 font-semibold text-slate-200">Net profit</td>
                                <td class="num px-5 py-3 text-right font-semibold {{ $profitable ? 'text-emerald-300' : 'text-rose-300' }}">
                                    {{ $profit >= 0 ? '+' : '' }}{{ $isk($profit) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
