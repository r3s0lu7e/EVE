<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Live market read-model for the product page. Wraps the public ESI market
 * endpoints with a local cache so repeated page views don't re-hit ESI: each
 * result is cached for roughly ESI's own cache window plus a little random
 * jitter, so refreshes land just after fresh data is published rather than
 * stampeding the shared cache boundary.
 */
class MarketService
{
    /** The Forge — the region containing Jita, EVE's main trade hub. */
    public const TRADE_HUB_REGION = 10000002;

    public function __construct(private EsiClient $esi)
    {
    }

    /**
     * Best buy/sell prices and depth for a type at the main trade hub.
     *
     * @return array{sell_min:?float,buy_max:?float,spread:?float,spread_pct:?float,sell_volume:int,buy_volume:int,sell_orders:int,buy_orders:int}
     */
    public function snapshot(int $typeId): array
    {
        // ESI caches orders for 300s; cache locally for that window + jitter.
        $orders = Cache::remember(
            "market:orders:{$typeId}",
            now()->addSeconds(random_int(300, 360)),
            fn () => $this->esi->marketOrders(self::TRADE_HUB_REGION, $typeId)
        );

        $sells = array_filter($orders, fn ($o) => empty($o['is_buy_order']));
        $buys = array_filter($orders, fn ($o) => ! empty($o['is_buy_order']));

        $sellMin = $sells ? min(array_column($sells, 'price')) : null;
        $buyMax = $buys ? max(array_column($buys, 'price')) : null;

        $spread = ($sellMin !== null && $buyMax !== null) ? $sellMin - $buyMax : null;
        $spreadPct = ($spread !== null && $sellMin > 0) ? ($spread / $sellMin) * 100 : null;

        return [
            'sell_min' => $sellMin !== null ? (float) $sellMin : null,
            'buy_max' => $buyMax !== null ? (float) $buyMax : null,
            'spread' => $spread,
            'spread_pct' => $spreadPct,
            'sell_volume' => (int) array_sum(array_column($sells, 'volume_remain')),
            'buy_volume' => (int) array_sum(array_column($buys, 'volume_remain')),
            'sell_orders' => count($sells),
            'buy_orders' => count($buys),
        ];
    }

    /**
     * Daily price/volume history (oldest→newest) for the last $days days.
     *
     * @return array<int,array{date:string,average:float,highest:float,lowest:float,volume:int}>
     */
    public function history(int $typeId, int $days = 90): array
    {
        // ESI refreshes history once a day; an hour of local cache (+ jitter) is
        // plenty and keeps the page snappy.
        $rows = Cache::remember(
            "market:history:{$typeId}",
            now()->addSeconds(random_int(3600, 4200)),
            fn () => $this->esi->marketHistory(self::TRADE_HUB_REGION, $typeId)
        );

        return collect($rows)
            ->sortBy('date')
            ->take(-$days)
            ->map(fn ($r) => [
                'date' => $r['date'],
                'average' => (float) $r['average'],
                'highest' => (float) $r['highest'],
                'lowest' => (float) $r['lowest'],
                'volume' => (int) $r['volume'],
            ])
            ->values()
            ->all();
    }

    /**
     * Static type detail (description, packaged volume). Cached long since this
     * effectively never changes.
     *
     * @return array{description:?string,volume:?float}|null
     */
    public function typeDetail(int $typeId): ?array
    {
        $type = Cache::remember(
            "market:type:{$typeId}",
            now()->addDay(),
            fn () => $this->esi->type($typeId)
        );

        if (! $type) {
            return null;
        }

        return [
            'description' => $type['description'] ?? null,
            'volume' => isset($type['volume']) ? (float) $type['volume'] : null,
        ];
    }
}
