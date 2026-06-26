<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\EveSyncService;
use Illuminate\Console\Command;

class EveSync extends Command
{
    protected $signature = 'eve:sync {--character= : Only sync this character id}';

    protected $description = 'Pull wallet transactions/journal from ESI and rebuild realized-profit data';

    public function handle(EveSyncService $sync): int
    {
        $characters = Character::query()
            ->when($this->option('character'), fn ($q, $id) => $q->where('character_id', $id))
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
