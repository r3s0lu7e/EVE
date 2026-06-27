<?php

namespace App\Services;

use App\Models\Character;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the EVE ESI REST API.
 *
 * Responsibilities:
 *  - attach the character's bearer token (refreshing via EveTokenService),
 *  - respect the ESI error-rate limit (X-ESI-Error-Limit-* headers),
 *  - expose the few endpoints the tracker needs, with pagination helpers.
 */
class EsiClient
{
    private const BASE = 'https://esi.evetech.net/latest';

    /**
     * Cache window (UTC) from the most recent wallet journal fetch, parsed from
     * the response Last-Modified / Expires headers. Null until walletJournal runs.
     *
     * @var array{as_of:?Carbon,expires:?Carbon}|null
     */
    private ?array $lastJournalCache = null;

    public function __construct(private EveTokenService $tokens)
    {
    }

    /**
     * The cache window of the last wallet journal response, or null.
     *
     * @return array{as_of:?Carbon,expires:?Carbon}|null
     */
    public function lastJournalCache(): ?array
    {
        return $this->lastJournalCache;
    }

    /* ----------------------------------------------------------------- *
     | Wallet                                                            |
     * ----------------------------------------------------------------- */

    /**
     * One page (up to 2500, newest first) of wallet transactions.
     * Pass $fromId to page backwards in time (transaction ids strictly below it).
     *
     * @return array<int,array<string,mixed>>
     */
    public function walletTransactions(Character $character, ?int $fromId = null): array
    {
        $query = $fromId ? ['from_id' => $fromId] : [];

        return $this->authGet($character, "/characters/{$character->character_id}/wallet/transactions/", $query)
            ->json();
    }

    /**
     * Full wallet journal across all pages (ESI retains ~30 days).
     *
     * @return array<int,array<string,mixed>>
     */
    public function walletJournal(Character $character): array
    {
        $entries = [];
        $page = 1;
        $path = "/characters/{$character->character_id}/wallet/journal/";

        do {
            $response = $this->authGet($character, $path, ['page' => $page]);

            // The cache window is identical across pages; capture it from page 1.
            if ($page === 1) {
                $this->lastJournalCache = [
                    'as_of' => $this->parseHttpDate($response->header('Last-Modified')),
                    'expires' => $this->parseHttpDate($response->header('Expires')),
                ];
            }

            $entries = array_merge($entries, $response->json());
            $pages = (int) ($response->header('X-Pages') ?: 1);
            $page++;
        } while ($page <= $pages);

        return $entries;
    }

    /* ----------------------------------------------------------------- *
     | Universe / name resolution                                        |
     * ----------------------------------------------------------------- */

    /**
     * Resolve a batch of ids (<=1000) to {id, name, category}. No auth required.
     *
     * @param  array<int,int>  $ids
     * @return array<int,array<string,mixed>>
     */
    public function resolveNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->backoff(
            fn () => Http::acceptJson()->post(self::BASE.'/universe/names/', array_values($ids))
        )->json();
    }

    /** Full type detail (includes group_id) — used to enrich group/category. */
    public function type(int $typeId): ?array
    {
        $response = $this->backoff(fn () => Http::acceptJson()->get(self::BASE."/universe/types/{$typeId}/"));

        return $response->successful() ? $response->json() : null;
    }

    public function group(int $groupId): ?array
    {
        $response = $this->backoff(fn () => Http::acceptJson()->get(self::BASE."/universe/groups/{$groupId}/"));

        return $response->successful() ? $response->json() : null;
    }

    public function category(int $categoryId): ?array
    {
        $response = $this->backoff(fn () => Http::acceptJson()->get(self::BASE."/universe/categories/{$categoryId}/"));

        return $response->successful() ? $response->json() : null;
    }

    /** NPC station (public). */
    public function station(int $stationId): ?array
    {
        $response = $this->backoff(fn () => Http::acceptJson()->get(self::BASE."/universe/stations/{$stationId}/"));

        return $response->successful() ? $response->json() : null;
    }

    /** Player structure (citadel) — needs the structures scope + auth. */
    public function structure(Character $character, int $structureId): ?array
    {
        $response = $this->rawAuthGet($character, "/universe/structures/{$structureId}/");

        return $response->successful() ? $response->json() : null;
    }

    public function system(int $systemId): ?array
    {
        $response = $this->backoff(fn () => Http::acceptJson()->get(self::BASE."/universe/systems/{$systemId}/"));

        return $response->successful() ? $response->json() : null;
    }

    public function constellation(int $constellationId): ?array
    {
        $response = $this->backoff(fn () => Http::acceptJson()->get(self::BASE."/universe/constellations/{$constellationId}/"));

        return $response->successful() ? $response->json() : null;
    }

    public function region(int $regionId): ?array
    {
        $response = $this->backoff(fn () => Http::acceptJson()->get(self::BASE."/universe/regions/{$regionId}/"));

        return $response->successful() ? $response->json() : null;
    }

    /* ----------------------------------------------------------------- *
     | Market (public)                                                   |
     * ----------------------------------------------------------------- */

    /**
     * Daily market history for a type in a region: each row has
     * {date, average, highest, lowest, order_count, volume}. ESI updates this
     * once a day. No auth required.
     *
     * @return array<int,array<string,mixed>>
     */
    public function marketHistory(int $regionId, int $typeId): array
    {
        $response = $this->backoff(
            fn () => Http::acceptJson()->get(self::BASE."/markets/{$regionId}/history/", ['type_id' => $typeId])
        );

        return $response->successful() ? $response->json() : [];
    }

    /**
     * Open market orders for a type in a region (both buy and sell), across all
     * pages. Each row has {is_buy_order, price, volume_remain, location_id, ...}.
     * No auth required.
     *
     * @return array<int,array<string,mixed>>
     */
    public function marketOrders(int $regionId, int $typeId): array
    {
        $path = "/markets/{$regionId}/orders/";
        $orders = [];
        $page = 1;

        do {
            $response = $this->backoff(
                fn () => Http::acceptJson()->get(self::BASE.$path, [
                    'type_id' => $typeId,
                    'order_type' => 'all',
                    'page' => $page,
                ])
            );

            if ($response->failed()) {
                break;
            }

            $orders = array_merge($orders, $response->json());
            $pages = (int) ($response->header('X-Pages') ?: 1);
            $page++;
        } while ($page <= $pages && $page <= 10);

        return $orders;
    }

    /* ----------------------------------------------------------------- *
     | Internals                                                         |
     * ----------------------------------------------------------------- */

    /** Parse an RFC 7231 HTTP-date header (always GMT) into a UTC Carbon, or null. */
    private function parseHttpDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Authenticated GET that throws on failure (used for required wallet data). */
    private function authGet(Character $character, string $path, array $query = []): Response
    {
        $response = $this->rawAuthGet($character, $path, $query);

        if ($response->failed()) {
            throw new RuntimeException("ESI GET {$path} failed: {$response->status()} {$response->body()}");
        }

        return $response;
    }

    /** Authenticated GET that returns the response as-is (caller decides). */
    private function rawAuthGet(Character $character, string $path, array $query = []): Response
    {
        $token = $this->tokens->validAccessToken($character);

        return $this->backoff(
            fn () => Http::withToken($token)->acceptJson()->get(self::BASE.$path, $query)
        );
    }

    /**
     * Execute a request, honouring the ESI error-rate limiter. If we're nearly
     * out of error budget (or get a 420), wait for the window to reset and retry.
     *
     * @param  callable():Response  $request
     */
    private function backoff(callable $request, int $attempts = 3): Response
    {
        $response = $request();

        for ($i = 0; $i < $attempts; $i++) {
            $remain = (int) ($response->header('X-Esi-Error-Limit-Remain') ?? 100);
            $reset = (int) ($response->header('X-Esi-Error-Limit-Reset') ?? 1);

            if ($response->status() === 420 || ($response->failed() && $remain <= 2)) {
                // Only the CLI sync (no user waiting) blocks for the window to
                // reset; a web request fails fast and lets the caller degrade
                // rather than freezing the page for up to a minute.
                if (! app()->runningInConsole()) {
                    break;
                }

                sleep(min($reset + 1, 60));
                $response = $request();

                continue;
            }

            break;
        }

        return $response;
    }
}
