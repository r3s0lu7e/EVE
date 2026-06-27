<?php

namespace App\Livewire;

use App\Models\Character;
use App\Services\AssetService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Net-worth valuation of a character's assets, priced at Jita via Fuzzwork.
 * Mirrors jEveAssets' core view: a total net worth plus a per-item breakdown,
 * with a sell/buy/split price basis you can switch between.
 */
class Assets extends Component
{
    use WithPagination;

    private const PER_PAGE = 50;

    private const BASES = ['sell', 'buy', 'split'];

    #[Url] #[\Livewire\Attributes\Session] public string $basis = 'sell';
    #[Url] #[\Livewire\Attributes\Session] public string $search = '';
    #[Url] #[\Livewire\Attributes\Session] public ?int $characterId = null;

    public ?string $message = null;

    /** Set for the single render after a manual refresh, to force a Tracker snapshot. */
    public bool $forceSnapshot = false;

    public function mount(): void
    {
        if ($this->characterId === null) {
            $this->characterId = Session::get('active_character_id');
        }
    }

    public function setBasis(string $basis): void
    {
        $this->basis = in_array($basis, self::BASES, true) ? $basis : 'sell';
        $this->resetPage();
    }

    public function updatedCharacterId(): void
    {
        $this->resetPage();
    }

    /** Commit the (deferred) search input — runs on submit / the Search button. */
    public function applyFilters(): void
    {
        $this->resetPage();
    }

    /** Drop the cached asset list so the next render re-fetches from ESI. */
    public function refreshAssets(AssetService $assets): void
    {
        $character = $this->character();
        if (! $character) {
            $this->message = 'Log in with EVE first.';

            return;
        }

        $assets->refresh($character);
        $this->forceSnapshot = true;
        $this->message = 'Assets refreshed from ESI.';
        $this->resetPage();
    }

    private function character(): ?Character
    {
        return $this->characterId ? Character::find($this->characterId) : null;
    }

    #[Layout('components.layouts.app')]
    public function render(AssetService $assets)
    {
        $character = $this->character();
        $valuation = null;
        $error = null;

        $history = [];

        if ($character) {
            try {
                $valuation = $assets->valuation($character, $this->basis, $this->forceSnapshot);
                $history = $assets->history($character);
            } catch (\Throwable $e) {
                // A missing assets scope surfaces here as a 403 from ESI.
                $error = $e->getMessage();
            }
        }

        // One-shot: only the render right after a manual refresh forces a snapshot.
        $this->forceSnapshot = false;

        // The headline net worth is always the full total; search only filters
        // the table below it.
        $filtered = collect($valuation['rows'] ?? [])
            ->when($this->search !== '', fn ($c) => $c->filter(
                fn ($r) => stripos($r['name'], $this->search) !== false
            ))
            ->values();

        $page = $this->getPage();
        $rows = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE)->values(),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return view('livewire.assets', [
            'characters' => Character::orderBy('name')->get(),
            'character' => $character,
            'valuation' => $valuation,
            'rows' => $rows,
            'history' => $history,
            'error' => $error,
        ]);
    }
}
