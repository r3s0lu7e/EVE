<?php

namespace App\Services;

use App\Models\Character;
use App\Models\EveType;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\Transaction;
use App\Services\CostBasis\ProfitEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pulls a character's wallet from ESI into the local archive, resolves any new
 * type/location names, then rebuilds realized-profit rows via the ProfitEngine.
 *
 * The local DB is the permanent record: ESI only retains ~30 days of wallet
 * data, so each run appends new rows and never deletes history.
 */
class EveSyncService
{
    /**
     * Random jitter (seconds) added to the ESI cache expiry to pick each
     * character's next-sync time. ESI publishes wallet data on round clock
     * boundaries, so polling exactly at expiry stampedes the API and can read
     * the still-stale copy before it regenerates. A few seconds to a couple of
     * minutes is negligible against the 1-hour cache and spreads the load.
     */
    private const SYNC_JITTER_MIN = 20;

    private const SYNC_JITTER_MAX = 120;

    public function __construct(
        private EsiClient $esi,
        private ProfitEngine $engine,
    ) {
    }

    /**
     * @return array{transactions:int,journal:int,matches:int}
     */
    public function syncCharacter(Character $character): array
    {
        $newTransactions = $this->syncTransactions($character);
        $journalCount = $this->syncJournal($character);

        $this->resolveTypes($character);
        $this->resolveLocations($character);

        $matchCount = $this->engine->rebuildForCharacter($character);

        $cache = $this->esi->lastJournalCache();
        $expires = $cache['expires'] ?? null;
        $character->forceFill([
            'last_synced_at' => now(),
            'wallet_as_of' => $cache['as_of'] ?? null,
            'wallet_expires_at' => $expires,
            'wallet_next_sync_at' => $expires
                ? $expires->copy()->addSeconds(random_int(self::SYNC_JITTER_MIN, self::SYNC_JITTER_MAX))
                : null,
        ])->save();

        return [
            'transactions' => $newTransactions,
            'journal' => $journalCount,
            'matches' => $matchCount,
        ];
    }

    /** Walk wallet transactions newest→oldest until we reach known territory. */
    private function syncTransactions(Character $character): int
    {
        $known = $character->last_transaction_id;
        $fromId = null;
        $collected = [];
        $reachedKnown = false;

        do {
            $page = $this->esi->walletTransactions($character, $fromId);
            if (empty($page)) {
                break;
            }

            foreach ($page as $txn) {
                if ($known !== null && $txn['transaction_id'] <= $known) {
                    $reachedKnown = true;

                    continue;
                }
                $collected[$txn['transaction_id']] = [
                    'transaction_id' => $txn['transaction_id'],
                    'character_id' => $character->character_id,
                    'date' => Carbon::parse($txn['date']),
                    'type_id' => $txn['type_id'],
                    'location_id' => $txn['location_id'] ?? null,
                    'quantity' => $txn['quantity'],
                    'unit_price' => $txn['unit_price'],
                    'is_buy' => $txn['is_buy'],
                    'client_id' => $txn['client_id'] ?? null,
                    'journal_ref_id' => $txn['journal_ref_id'] ?? null,
                ];
            }

            $fromId = min(array_column($page, 'transaction_id'));
            // A short page means we've hit the end of available history.
            $endOfHistory = count($page) < 2500;
        } while (! $reachedKnown && ! $endOfHistory);

        if (! empty($collected)) {
            foreach (array_chunk($collected, 500, true) as $chunk) {
                Transaction::upsert(
                    array_values($chunk),
                    ['transaction_id'],
                    ['character_id', 'date', 'type_id', 'location_id', 'quantity', 'unit_price', 'is_buy', 'client_id', 'journal_ref_id']
                );
            }

            $character->forceFill([
                'last_transaction_id' => max(array_keys($collected), [$known ?? 0]),
            ])->save();
        }

        return count($collected);
    }

    private function syncJournal(Character $character): int
    {
        $entries = $this->esi->walletJournal($character);
        if (empty($entries)) {
            return 0;
        }

        $rows = array_map(fn ($e) => [
            'id' => $e['id'],
            'character_id' => $character->character_id,
            'date' => Carbon::parse($e['date']),
            'ref_type' => $e['ref_type'],
            'amount' => $e['amount'] ?? null,
            'balance' => $e['balance'] ?? null,
            'context_id' => $e['context_id'] ?? null,
            'context_id_type' => $e['context_id_type'] ?? null,
            'reason' => $e['reason'] ?? null,
            'description' => $e['description'] ?? null,
        ], $entries);

        foreach (array_chunk($rows, 500) as $chunk) {
            JournalEntry::upsert(
                $chunk,
                ['id'],
                ['character_id', 'date', 'ref_type', 'amount', 'balance', 'context_id', 'context_id_type', 'reason', 'description']
            );
        }

        return count($rows);
    }

    /** Resolve names (and best-effort group/category) for any unseen type ids. */
    private function resolveTypes(Character $character): void
    {
        $needed = Transaction::where('character_id', $character->character_id)
            ->whereNotIn('type_id', EveType::query()->select('type_id'))
            ->distinct()
            ->pluck('type_id')
            ->all();

        if (empty($needed)) {
            return;
        }

        foreach (array_chunk($needed, 1000) as $chunk) {
            foreach ($this->esi->resolveNames($chunk) as $named) {
                EveType::updateOrCreate(
                    ['type_id' => $named['id']],
                    ['name' => $named['name']]
                );
            }
        }

        // Best-effort enrichment with group/category (cached forever once filled).
        foreach ($needed as $typeId) {
            try {
                $type = $this->esi->type($typeId);
                if (! $type || ! isset($type['group_id'])) {
                    continue;
                }
                $group = $this->esi->group($type['group_id']);
                $categoryName = null;
                if ($group && isset($group['category_id'])) {
                    $category = $this->esi->category($group['category_id']);
                    $categoryName = $category['name'] ?? null;
                }
                EveType::where('type_id', $typeId)->update([
                    'group_id' => $type['group_id'],
                    'group_name' => $group['name'] ?? null,
                    'category_name' => $categoryName,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Type enrichment failed for {$typeId}: {$e->getMessage()}");
            }
        }
    }

    /** Resolve station/structure names + system/region for any unseen locations. */
    private function resolveLocations(Character $character): void
    {
        $needed = Transaction::where('character_id', $character->character_id)
            ->whereNotNull('location_id')
            ->whereNotIn('location_id', Location::query()->select('location_id'))
            ->distinct()
            ->pluck('location_id')
            ->all();

        foreach ($needed as $locationId) {
            try {
                // Heuristic: ids below 100bn are NPC stations; above are player structures.
                $info = $locationId < 100_000_000_000
                    ? $this->esi->station((int) $locationId)
                    : $this->esi->structure($character, (int) $locationId);

                if (! $info) {
                    Location::updateOrCreate(['location_id' => $locationId], []);

                    continue;
                }

                $systemId = $info['system_id'] ?? $info['solar_system_id'] ?? null;
                [$systemName, $regionId, $regionName] = $this->resolveSystem($systemId);

                Location::updateOrCreate(['location_id' => $locationId], [
                    'name' => $info['name'] ?? null,
                    'system_id' => $systemId,
                    'system_name' => $systemName,
                    'region_id' => $regionId,
                    'region_name' => $regionName,
                ]);
            } catch (\Throwable $e) {
                Log::warning("Location resolution failed for {$locationId}: {$e->getMessage()}");
            }
        }
    }

    /**
     * @return array{0:?string,1:?int,2:?string}  [systemName, regionId, regionName]
     */
    private function resolveSystem(?int $systemId): array
    {
        if (! $systemId) {
            return [null, null, null];
        }

        $system = $this->esi->system($systemId);
        if (! $system) {
            return [null, null, null];
        }

        $regionId = null;
        $regionName = null;
        if (isset($system['constellation_id'])) {
            $constellation = $this->esi->constellation($system['constellation_id']);
            if ($constellation && isset($constellation['region_id'])) {
                $regionId = $constellation['region_id'];
                $region = $this->esi->region($regionId);
                $regionName = $region['name'] ?? null;
            }
        }

        return [$system['name'] ?? null, $regionId, $regionName];
    }
}
