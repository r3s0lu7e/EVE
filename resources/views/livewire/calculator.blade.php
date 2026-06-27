@php
    $isk = fn ($v) => number_format((float) $v, 2);
    $r = $this->result;
    $profit = $r['profit'] ?? 0.0;
    $profitable = $profit > 0;
    $qty = max(1.0, (float) str_replace([' ', ','], ['', '.'], $this->quantity));

    // Effective per-side rates, for the collapsed settings summary.
    $f = fn ($v) => (float) str_replace(',', '.', $v);
    $buyFeePct = $f($this->buyBrokerFee) + $f($this->sccSurcharge);
    $sellFeePct = $f($this->sellBrokerFee) + $f($this->sccSurcharge) + $f($this->salesTax);
@endphp

<div wire:key="calculator"
     x-data="{
         showSettings: true,
         // Space-group the integer part so long ISK figures stay countable.
         fmt(v) {
             const parts = String(v).replace(/\s/g, '').split(/[.,]/);
             const int = parts[0].replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
             return parts.length > 1 ? int + ',' + parts.slice(1).join('').replace(/\D/g, '') : int;
         },
         group(el) {
             const caret = el.selectionStart ?? el.value.length;
             const digitsBefore = el.value.slice(0, caret).replace(/\D/g, '').length;
             el.value = this.fmt(el.value);
             let count = 0, pos = 0;
             while (pos < el.value.length && count < digitsBefore) {
                 if (/\d/.test(el.value[pos])) count++;
                 pos++;
             }
             el.setSelectionRange(pos, pos);
         },
     }"
     x-on:focus.capture="$event.target.matches('input') && $event.target.select()"
     class="mx-auto max-w-xl space-y-4">
    <div class="flex items-baseline justify-between">
        <h1 class="text-lg font-semibold tracking-tight text-slate-100">Margin Calculator</h1>
        <span class="text-xs text-slate-500">Buy {{ rtrim(rtrim(number_format($buyFeePct, 2), '0'), '.') }}% · Sell {{ rtrim(rtrim(number_format($sellFeePct, 2), '0'), '.') }}%</span>
    </div>

    {{-- Prices: the only thing you touch for a quick check --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="buyPrice" class="block text-xs font-medium text-slate-400">Buy price</label>
            <input id="buyPrice" type="text" inputmode="decimal" autocomplete="off"
                   x-ref="buy" x-init="$el.value = fmt($el.value); $nextTick(() => $refs.buy.focus())"
                   x-on:input="group($event.target)"
                   wire:model.live.debounce.250ms="buyPrice" placeholder="509 700"
                   class="num mt-1 w-full rounded-lg border border-slate-700 bg-space-800 px-3 py-2.5 text-base text-slate-100 focus:border-eve-400 focus:ring-0">
        </div>
        <div>
            <label for="sellPrice" class="block text-xs font-medium text-slate-400">Sell price</label>
            <input id="sellPrice" type="text" inputmode="decimal" autocomplete="off"
                   x-init="$el.value = fmt($el.value)"
                   x-on:input="group($event.target)"
                   wire:model.live.debounce.250ms="sellPrice" placeholder="824 700"
                   class="num mt-1 w-full rounded-lg border border-slate-700 bg-space-800 px-3 py-2.5 text-base text-slate-100 focus:border-eve-400 focus:ring-0">
        </div>
    </div>

    {{-- Result: margin is the hero --}}
    @if(! $r)
        <div class="flex min-h-[8rem] items-center justify-center rounded-xl border border-slate-800/80 bg-space-850/50 px-6 py-10 text-center">
            <p class="text-sm text-slate-500">Enter a buy and sell price.</p>
        </div>
    @else
        <div class="rounded-xl border px-5 py-5 {{ $profitable ? 'border-emerald-600/40 bg-emerald-950/30' : 'border-rose-600/40 bg-rose-950/30' }}">
            <div class="flex items-end justify-between gap-3">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide {{ $profitable ? 'text-emerald-300' : 'text-rose-300' }}">
                        {{ $profitable ? 'Profitable' : 'Loss' }} · margin
                    </p>
                    <p class="num text-5xl font-semibold leading-none {{ $profitable ? 'text-emerald-200' : 'text-rose-200' }}">
                        {{ number_format($r['margin'], 1) }}<span class="text-2xl">%</span>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-[11px] text-slate-400">Profit / unit</p>
                    <p class="num text-xl font-semibold {{ $profitable ? 'text-emerald-200' : 'text-rose-200' }}">
                        {{ $profit >= 0 ? '+' : '' }}{{ $isk($r['profit_per_unit']) }}
                    </p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 border-t border-current/10 pt-3 text-xs text-slate-400">
                <span>Break-even sell: <span class="num text-slate-200">{{ $isk($r['break_even']) }}</span></span>
                @if($qty > 1)
                    <span>Total profit (×{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}): <span class="num {{ $profitable ? 'text-emerald-300' : 'text-rose-300' }}">{{ $profit >= 0 ? '+' : '' }}{{ $isk($profit) }}</span></span>
                @endif
                <span>Fees: <span class="num text-rose-300">{{ $isk($r['total_fees']) }}</span></span>
            </div>
        </div>
    @endif

    {{-- Settings & breakdown: configured once, then forgotten --}}
    <div class="rounded-xl border border-slate-800/80 bg-space-850/50">
        <button type="button" @click="showSettings = !showSettings"
                class="flex w-full items-center justify-between px-5 py-3 text-sm text-slate-300 hover:text-slate-100">
            <span class="font-medium">Settings &amp; breakdown</span>
            <svg class="h-4 w-4 transition-transform" :class="showSettings && 'rotate-180'" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>

        <div x-show="showSettings" x-collapse x-cloak class="border-t border-slate-800/80 px-5 py-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div>
                    <label for="quantity" class="block text-xs font-medium text-slate-400">Quantity</label>
                    <input id="quantity" type="text" inputmode="numeric"
                           wire:model.live.debounce.300ms="quantity"
                           class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                </div>
                <div>
                    <label for="buyBrokerFee" class="block text-xs font-medium text-slate-400">Buy broker %</label>
                    <input id="buyBrokerFee" type="text" inputmode="decimal" wire:model.live.debounce.300ms="buyBrokerFee"
                           class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                </div>
                <div>
                    <label for="sellBrokerFee" class="block text-xs font-medium text-slate-400">Sell broker %</label>
                    <input id="sellBrokerFee" type="text" inputmode="decimal" wire:model.live.debounce.300ms="sellBrokerFee"
                           class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                </div>
                <div>
                    <label for="sccSurcharge" class="block text-xs font-medium text-slate-400">SCC %</label>
                    <input id="sccSurcharge" type="text" inputmode="decimal" wire:model.live.debounce.300ms="sccSurcharge"
                           class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                </div>
                <div>
                    <label for="salesTax" class="block text-xs font-medium text-slate-400">Sales tax %</label>
                    <input id="salesTax" type="text" inputmode="decimal" wire:model.live.debounce.300ms="salesTax"
                           class="num mt-1 w-full rounded-md border border-slate-700 bg-space-800 px-3 py-2 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
                </div>
            </div>

            @if($r)
                <table class="mt-4 w-full border-t border-slate-800/80 pt-2 text-sm">
                    <tbody class="divide-y divide-slate-800/70">
                        <tr>
                            <td class="py-2 text-slate-400">Buy cost</td>
                            <td class="num py-2 text-right text-slate-200">{{ $isk($r['buy_cost']) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pl-4 text-slate-500">+ Broker fee (buy)</td>
                            <td class="num py-2 text-right text-rose-300">{{ $isk($r['buy_broker']) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pl-4 text-slate-500">+ SCC surcharge (buy)</td>
                            <td class="num py-2 text-right text-rose-300">{{ $isk($r['buy_scc']) }}</td>
                        </tr>
                        <tr class="bg-space-800/40">
                            <td class="py-2 font-medium text-slate-300">Total invested</td>
                            <td class="num py-2 text-right font-medium text-slate-100">{{ $isk($r['invested']) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 text-slate-400">Sell revenue</td>
                            <td class="num py-2 text-right text-slate-200">{{ $isk($r['revenue']) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pl-4 text-slate-500">− Broker fee (sell)</td>
                            <td class="num py-2 text-right text-rose-300">{{ $isk($r['sell_broker']) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pl-4 text-slate-500">− SCC surcharge (sell)</td>
                            <td class="num py-2 text-right text-rose-300">{{ $isk($r['sell_scc']) }}</td>
                        </tr>
                        <tr>
                            <td class="py-2 pl-4 text-slate-500">− Sales tax</td>
                            <td class="num py-2 text-right text-rose-300">{{ $isk($r['tax']) }}</td>
                        </tr>
                        <tr class="bg-space-800/40">
                            <td class="py-2 font-medium text-slate-300">Net received</td>
                            <td class="num py-2 text-right font-medium text-slate-100">{{ $isk($r['received']) }}</td>
                        </tr>
                        <tr class="{{ $profitable ? 'bg-emerald-950/20' : 'bg-rose-950/20' }}">
                            <td class="py-2.5 font-semibold text-slate-200">Net profit</td>
                            <td class="num py-2.5 text-right font-semibold {{ $profitable ? 'text-emerald-300' : 'text-rose-300' }}">
                                {{ $profit >= 0 ? '+' : '' }}{{ $isk($profit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
