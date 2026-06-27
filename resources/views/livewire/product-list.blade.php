@php
    $isk = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
@endphp

<div wire:key="product-list" class="space-y-6">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-slate-100">Products</h1>
            <p class="mt-0.5 text-xs text-slate-500">Every item you've traded · all-time realized profit · <span class="num">{{ $rows->total() }}</span> items</p>
        </div>
        <label class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"/><path d="m20 20-3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="search" placeholder="Search items…" wire:model.live.debounce.300ms="search"
                   class="w-64 rounded-md border border-slate-700 bg-space-800 py-2 pl-8 pr-2.5 text-sm text-slate-100 focus:border-eve-400 focus:ring-0">
        </label>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-slate-800/80 bg-space-850/50">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-slate-400">
                    @php
                        $cols = [
                            'label' => ['Item', 'left'],
                            'net_profit' => ['Net profit', 'right'],
                            'gross_profit' => ['Gross', 'right'],
                            'fees' => ['Fees', 'right'],
                            'quantity' => ['Qty', 'right'],
                            'sales' => ['Sales', 'right'],
                            'last_traded' => ['Last traded', 'right'],
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
                        <tr wire:key="prod-{{ $row->type_id }}" class="transition-colors hover:bg-eve-400/[.04]">
                            <td class="px-4 py-2.5">
                                <a href="{{ route('product.show', $row->type_id) }}" wire:navigate
                                   class="group inline-flex items-center gap-2.5">
                                    <img src="https://images.evetech.net/types/{{ $row->type_id }}/icon?size=32" alt=""
                                         width="32" height="32" loading="lazy"
                                         class="h-8 w-8 shrink-0 rounded border border-slate-800 bg-space-900">
                                    <span class="min-w-0">
                                        <span class="block truncate text-eve-300 transition-colors group-hover:text-eve-400 group-hover:underline">{{ $row->label }}</span>
                                        @if($row->group_name)
                                            <span class="block truncate text-[11px] text-slate-500">{{ $row->group_name }}</span>
                                        @endif
                                    </span>
                                </a>
                            </td>
                            <td class="num px-4 py-2.5 text-right font-medium {{ $row->net_profit >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $isk($row->net_profit) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-300">{{ $isk($row->gross_profit) }}</td>
                            <td class="num px-4 py-2.5 text-right text-amber-400/90">{{ $isk($row->fees) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-400">{{ number_format($row->quantity) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-400">{{ number_format($row->sales) }}</td>
                            <td class="num px-4 py-2.5 text-right text-slate-500" x-data x-text="fmtLocal(@js($row->last_traded), 'date')"></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <svg class="mx-auto h-8 w-8 text-slate-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                <p class="mt-2 text-sm text-slate-500">{{ $search !== '' ? 'No items match your search.' : 'No traded items yet — sync your wallet to get started.' }}</p>
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
</div>
