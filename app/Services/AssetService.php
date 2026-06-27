<?php

namespace App\Services;

use App\Models\AssetSnapshot;
use App\Models\Character;
use App\Models\EveType;
use Illuminate\Support\Facades\Cache;

/**
 * Values a character's assets at Jita (à la jEveAssets): pulls the live asset
 * list from ESI, aggregates it by item type, resolves any unseen type names,
 * and prices everything in one bulk Fuzzwork call. The asset list is cached for
 * roughly ESI's own window so repeated page views don't re-hit the API.
 */
class AssetService
{
    /** Minimum gap between automatic (non-forced) Tracker snapshots. */
    private const SNAPSHOT_INTERVAL_MINUTES = 55;

    public function __construct(
        private EsiClient $esi,
        private MarketService $market,
    ) {
    }

    /**
     * Net-worth valuation aggregated by type, valued with $basis:
     *  - 'sell'  → lowest sell (replacement cost)
     *  - 'buy'   → highest buy (liquidation value)
     *  - 'split' → midpoint of the two
     *
     * `total` is the full net worth: priced assets plus liquid wallet ISK.
     *
     * Each call also records a Tracker snapshot (valued at Jita sell, so the
     * history stays consistent whatever basis is on screen): at most hourly,
     * and only when the value has moved — unless $forceSnapshot is set, which a
     * manual refresh uses to capture the change immediately.
     *
     * @return array{
     *   rows: array<int,array{type_id:int,name:string,group_name:?string,quantity:int,unit_price:float,value:float}>,
     *   assets_value: float, wallet: float, total: float, items: int, types: int, unpriced: int
     * }
     */
    public function valuation(Character $character, string $basis = 'sell', bool $forceSnapshot = false): array
    {
        $wallet = $this->cachedWalletBalance($character);
        $assets = $this->cachedAssets($character);

        // Aggregate quantity per type. Containers/ships appear as their own type
        // rows here, and their contents appear as separate rows, so summing the
        // flat list values everything you own exactly once.
        $byType = [];
        foreach ($assets as $item) {
            $typeId = (int) $item['type_id'];
            $byType[$typeId] = ($byType[$typeId] ?? 0) + (int) ($item['quantity'] ?? 1);
        }

        if (empty($byType)) {
            $this->recordSnapshot($character, $wallet, 0.0, $wallet, $forceSnapshot);

            return ['rows' => [], 'assets_value' => 0.0, 'wallet' => $wallet, 'total' => $wallet, 'items' => 0, 'types' => 0, 'unpriced' => 0];
        }

        $typeIds = array_keys($byType);
        $names = $this->resolveNames($typeIds);
        $prices = $this->market->bulkPrices($typeIds);

        $rows = [];
        $total = 0.0;
        $sellTotal = 0.0;
        $unpriced = 0;

        foreach ($byType as $typeId => $quantity) {
            $price = $prices[$typeId] ?? null;
            $unit = $this->basisPrice($price, $basis);
            // Canonical sell value drives the Tracker series regardless of basis.
            $sellTotal += ($this->basisPrice($price, 'sell') ?? 0.0) * $quantity;

            if ($unit === null) {
                $unpriced++;
                $unit = 0.0;
            }

            $value = $unit * $quantity;
            $total += $value;
            $meta = $names[$typeId] ?? null;

            $rows[] = [
                'type_id' => $typeId,
                'name' => $meta['name'] ?? "Type {$typeId}",
                'group_name' => $meta['group_name'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unit,
                'value' => $value,
            ];
        }

        usort($rows, fn ($a, $b) => $b['value'] <=> $a['value']);

        $this->recordSnapshot($character, $sellTotal + $wallet, $sellTotal, $wallet, $forceSnapshot);

        return [
            'rows' => $rows,
            'assets_value' => $total,
            'wallet' => $wallet,
            'total' => $total + $wallet,
            'items' => (int) array_sum($byType),
            'types' => count($byType),
            'unpriced' => $unpriced,
        ];
    }

    /**
     * Tracker history (oldest → newest) for the character's net worth.
     *
     * @return array<int,array{t:string,total:float,assets:float,wallet:float}>
     */
    public function history(Character $character): array
    {
        return AssetSnapshot::where('character_id', $character->character_id)
            ->orderBy('captured_at')
            ->get(['captured_at', 'total', 'assets_value', 'wallet'])
            ->map(fn (AssetSnapshot $s) => [
                // ISO8601 UTC; the chart renders points in the viewer's timezone.
                't' => $s->captured_at->toIso8601String(),
                'total' => $s->total,
                'assets' => $s->assets_value,
                'wallet' => $s->wallet,
            ])
            ->all();
    }

    /**
     * Append a Tracker snapshot when the net worth has changed since the last
     * reading. Automatic captures are throttled to roughly hourly; a manual
     * refresh ($force) records the change straight away.
     */
    private function recordSnapshot(Character $character, float $total, float $assetsValue, float $wallet, bool $force): void
    {
        $last = AssetSnapshot::where('character_id', $character->character_id)
            ->latest('captured_at')
            ->first();

        // Skip flat duplicate points — only record genuine movement.
        if ($last && abs($last->total - $total) < 0.01) {
            return;
        }

        if (! $force && $last && $last->captured_at->gt(now()->subMinutes(self::SNAPSHOT_INTERVAL_MINUTES))) {
            return;
        }

        AssetSnapshot::create([
            'character_id' => $character->character_id,
            'total' => round($total, 2),
            'assets_value' => round($assetsValue, 2),
            'wallet' => round($wallet, 2),
            'captured_at' => now(),
        ]);
    }

    /** Drop the cached asset list + wallet so the next valuation re-fetches from ESI. */
    public function refresh(Character $character): void
    {
        Cache::forget($this->assetsCacheKey($character));
        Cache::forget($this->walletCacheKey($character));
    }

    private function cachedAssets(Character $character): array
    {
        // ESI caches assets for ~1h; mirror that locally with a little jitter.
        return Cache::remember(
            $this->assetsCacheKey($character),
            now()->addSeconds(random_int(3600, 3900)),
            fn () => $this->esi->assets($character)
        );
    }

    private function cachedWalletBalance(Character $character): float
    {
        // ESI caches the wallet balance for ~120s; mirror that locally.
        return Cache::remember(
            $this->walletCacheKey($character),
            now()->addSeconds(random_int(120, 150)),
            fn () => $this->esi->walletBalance($character)
        );
    }

    private function assetsCacheKey(Character $character): string
    {
        return "assets:{$character->character_id}";
    }

    private function walletCacheKey(Character $character): string
    {
        return "assets:wallet:{$character->character_id}";
    }

    /** Resolve a per-type price for the chosen basis, or null if unpriced. */
    private function basisPrice(?array $price, string $basis): ?float
    {
        if (! $price) {
            return null;
        }

        $buy = (float) ($price['buy'] ?? 0);
        $sell = (float) ($price['sell'] ?? 0);

        $value = match ($basis) {
            'buy' => $buy,
            'split' => ($buy + $sell) / 2,
            default => $sell,
        };

        return $value > 0 ? $value : null;
    }

    /**
     * Names + group for the given type ids, resolving any unseen ids via ESI
     * and persisting them so later loads (and the rest of the app) hit the
     * local table.
     *
     * @param  array<int,int>  $typeIds
     * @return array<int,array{name:string,group_name:?string}>
     */
    private function resolveNames(array $typeIds): array
    {
        $known = EveType::whereIn('type_id', $typeIds)->pluck('type_id')->all();
        $missing = array_values(array_diff($typeIds, $known));

        foreach (array_chunk($missing, 1000) as $chunk) {
            foreach ($this->esi->resolveNames($chunk) as $named) {
                EveType::updateOrCreate(['type_id' => $named['id']], ['name' => $named['name']]);
            }
        }

        return EveType::whereIn('type_id', $typeIds)
            ->get(['type_id', 'name', 'group_name'])
            ->keyBy('type_id')
            ->map(fn ($t) => ['name' => $t->name, 'group_name' => $t->group_name])
            ->all();
    }
}
