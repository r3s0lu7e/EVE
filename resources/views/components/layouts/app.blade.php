<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'EVE Trade Tracker' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600;700&family=Fira+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tailwind + ApexCharts via CDN so the app runs without a Vite build.
         For a production build, swap these for @vite([...]). --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Fira Sans', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['Fira Code', 'ui-monospace', 'SFMono-Regular', 'monospace'],
                    },
                    colors: {
                        // New Eden palette: deep space navy + hologram cyan.
                        space: {
                            900: '#070b12',
                            850: '#0a0f18',
                            800: '#0e1521',
                            700: '#16203033',
                        },
                        eve: {
                            400: '#38d3ee',
                            500: '#22b8d6',
                            600: '#1797b4',
                        },
                    },
                    boxShadow: {
                        glow: '0 0 0 1px rgba(56,211,238,.10), 0 18px 40px -22px rgba(56,211,238,.45)',
                    },
                },
            },
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>

    {{-- Flatpickr: a lightweight, themeable date picker. Lets us display d/m/Y
         while keeping the Y-m-d value Livewire/the backend expects. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>

    <style>
        body {
            background-color: #070b12;
            background-image:
                radial-gradient(60rem 40rem at 80% -10%, rgba(56,211,238,.08), transparent 60%),
                radial-gradient(50rem 40rem at -10% 10%, rgba(37,99,235,.10), transparent 55%);
            background-attachment: fixed;
        }
        /* Mono tabular figures for all financial numbers. */
        .num { font-family: 'Fira Code', monospace; font-variant-numeric: tabular-nums; }
        /* Visible keyboard focus everywhere. */
        :focus-visible { outline: 2px solid #38d3ee; outline-offset: 2px; border-radius: 4px; }
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: .001ms !important; transition-duration: .001ms !important; }
        }
        [x-cloak] { display: none !important; }

        /* Flatpickr calendar themed for the New Eden palette. */
        .flatpickr-calendar {
            background: #0a0f18;
            border: 1px solid rgba(56, 211, 238, .15);
            border-radius: .75rem;
            box-shadow: 0 18px 40px -22px rgba(56, 211, 238, .45), 0 0 0 1px rgba(56, 211, 238, .08);
            color: #e2e8f0;
        }
        .flatpickr-calendar.arrowTop::before, .flatpickr-calendar.arrowTop::after,
        .flatpickr-calendar.arrowBottom::before, .flatpickr-calendar.arrowBottom::after { display: none; }
        .flatpickr-months, .flatpickr-month { background: transparent; color: #7dd3fc; fill: #7dd3fc; }
        .flatpickr-current-month { font-size: 1rem; font-weight: 600; }
        .flatpickr-current-month .flatpickr-monthDropdown-months {
            background: #0a0f18;
            color: #7dd3fc;
            font-weight: 600;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months:hover { background: rgba(56, 211, 238, .12); }
        .flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month { background: #0a0f18; color: #7dd3fc; }
        .flatpickr-current-month input.cur-year { color: #7dd3fc; font-weight: 600; }
        .flatpickr-weekdays span.flatpickr-weekday { color: #7dd3fc; font-weight: 600; }
        .flatpickr-prev-month svg, .flatpickr-next-month svg { fill: #94a3b8; }
        .flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: #38d3ee; }
        .flatpickr-day {
            color: #cbd5e1;
            border-radius: .375rem;
            border-color: transparent;
        }
        .flatpickr-day:hover, .flatpickr-day:focus { background: rgba(56, 211, 238, .12); border-color: transparent; }
        .flatpickr-day.today { border-color: rgba(56, 211, 238, .5); }
        .flatpickr-day.selected, .flatpickr-day.selected:hover {
            background: #22b8d6;
            border-color: #22b8d6;
            color: #070b12;
            font-weight: 600;
        }
        .flatpickr-day.flatpickr-disabled, .flatpickr-day.prevMonthDay, .flatpickr-day.nextMonthDay { color: #475569; }
        .flatpickr-day.inRange { background: rgba(56, 211, 238, .12); border-color: transparent; box-shadow: none; }
    </style>

    @livewireStyles
</head>
<body class="h-full font-sans text-slate-200 antialiased">
    <div class="min-h-full">
        <header class="sticky top-0 z-40 border-b border-eve-400/10 bg-space-900/80 backdrop-blur">
            <div class="mx-auto flex max-w-[90rem] items-center justify-between px-4 py-3 sm:px-6">
                <div class="flex items-center gap-6">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                        {{-- Hexagonal "New Eden" mark --}}
                        <svg class="h-7 w-7 text-eve-400" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 2.5 20 7v10l-8 4.5L4 17V7l8-4.5Z" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M12 7.5 16 10v4l-4 2.2L8 14v-4l4-2.5Z" fill="currentColor" fill-opacity=".25" stroke="currentColor" stroke-width="1"/>
                        </svg>
                        <span class="text-sm font-semibold tracking-wide text-slate-100">
                            EVE <span class="text-eve-400">Trade Ledger</span>
                        </span>
                    </a>

                    <nav class="hidden items-center gap-1 sm:flex" aria-label="Primary">
                        @php
                            $nav = [
                                ['Overview', 'dashboard', request()->routeIs('dashboard')],
                                ['Products', 'products.index', request()->routeIs('products.index') || request()->routeIs('product.show')],
                            ];
                        @endphp
                        @foreach($nav as [$label, $routeName, $active])
                            <a href="{{ route($routeName) }}" wire:navigate
                               @if($active) aria-current="page" @endif
                               class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors {{ $active ? 'bg-eve-500/10 text-eve-300' : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </nav>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    @if(session('active_character_id'))
                        <span class="hidden items-center gap-2 sm:flex">
                            <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px_2px_rgba(52,211,153,.6)]"></span>
                            <span class="text-slate-400">{{ \App\Models\Character::find(session('active_character_id'))?->name }}</span>
                        </span>
                        <form method="POST" action="{{ route('eve.logout') }}">
                            @csrf
                            <button class="rounded-md border border-slate-700/70 px-3 py-1.5 text-slate-300 transition-colors hover:bg-slate-800 cursor-pointer">
                                Log out
                            </button>
                        </form>
                    @else
                        <a href="{{ route('eve.redirect') }}"
                           class="rounded-md bg-eve-500 px-3.5 py-1.5 font-medium text-space-900 shadow-glow transition-colors hover:bg-eve-400">
                            Log in with EVE
                        </a>
                    @endif
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[90rem] px-4 py-6 sm:px-6">
            @if (session('status'))
                <div class="mb-4 flex items-center gap-2 rounded-lg border border-emerald-600/40 bg-emerald-950/40 px-4 py-2.5 text-sm text-emerald-200">
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 0 1 1.4-1.4l3.3 3.3 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Render server UTC timestamps in the viewer's own timezone (zero-config). --}}
    <script>
        window.fmtLocal = function (iso, mode = 'datetime') {
            if (!iso) return '—';
            const d = new Date(iso);
            if (isNaN(d)) return '—';
            const opts = mode === 'date'
                ? { day: '2-digit', month: 'short', year: 'numeric' }
                : { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };
            return new Intl.DateTimeFormat(undefined, opts).format(d);
        };
        window.fmtLocalRelative = function (iso) {
            if (!iso) return '';
            const diffMs = new Date(iso).getTime() - Date.now();
            const mins = Math.round(diffMs / 60000);
            const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
            if (Math.abs(mins) >= 60) return rtf.format(Math.round(mins / 60), 'hour');
            return rtf.format(mins, 'minute');
        };
        window.localTzName = Intl.DateTimeFormat().resolvedOptions().timeZone;
    </script>

    {{-- Ctrl/Cmd+F focuses the page's search box (if it has one) instead of the
         browser's native find. Registered once on the persistent layout, so it
         keeps working across wire:navigate page changes. --}}
    <script>
        document.addEventListener('keydown', (e) => {
            const isFind = (e.ctrlKey || e.metaKey) && (e.key === 'f' || e.key === 'F');
            if (!isFind) return;
            const box = document.querySelector('[data-search-focus]');
            if (!box) return;
            e.preventDefault();
            box.focus();
            box.select?.();
        });
    </script>

    @livewireScripts
</body>
</html>
