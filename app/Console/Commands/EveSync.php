<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\EveSyncService;
use Illuminate\Console\Command;

class EveSync extends Command
{
    protected $signature = 'eve:sync {--character= : Only sync this character id} {--due : Skip characters whose ESI cache window has not expired yet}';

    protected $description = 'Pull wallet transactions/journal from ESI and rebuild realized-profit data';

    public function handle(EveSyncService $sync): int
    {
        $characters = Character::query()
            ->when($this->option('character'), fn ($q, $id) => $q->where('character_id', $id))
            // --due: only characters past their randomized next-sync time (or
            // never synced). wallet_next_sync_at is the ESI Expires plus a small
            // random jitter, so we fetch just after fresh data is published —
            // spread off the shared cache boundary rather than stampeding it.
            ->when($this->option('due'), fn ($q) => $q->where(
                fn ($q) => $q->whereNull('wallet_next_sync_at')
                    ->orWhere('wallet_next_sync_at', '<=', now())
            ))
            ->get();

        if ($characters->isEmpty()) {
            $this->warn('No characters to sync. Log in via EVE SSO first.');

            return self::SUCCESS;
        }

        foreach ($characters as $character) {
            $this->info("Syncing {$character->name} ({$character->character_id})...");

            try {
                $result = $sync->syncCharacter($character);
                $this->line("  +{$result['transactions']} transactions, {$result['journal']} journal entries, {$result['matches']} matches.");
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
