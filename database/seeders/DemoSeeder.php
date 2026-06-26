<?php

namespace Database\Seeders;

use App\Models\Character;
use App\Models\EveType;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\Transaction;
use App\Services\CostBasis\ProfitEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Generates fake-but-plausible wallet data so the dashboard can be previewed
 * without an EVE login. Run: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $character = Character::updateOrCreate(
            ['character_id' => 91000001],
            ['name' => 'Demo Trader']
        );

        $types = [
            34 => 'Tritanium',
            35 => 'Pyerite',
            36 => 'Mexallon',
            44992 => 'PLEX',
            12005 => 'Ishtar',
        ];
        foreach ($types as $id => $name) {
            EveType::updateOrCreate(['type_id' => $id], ['name' => $name, 'group_name' => 'Demo', 'category_name' => 'Demo']);
        }

        Location::updateOrCreate(['location_id' => 60003760], [
            'name' => 'Jita IV - Moon 4 - Caldari Navy Assembly Plant',
            'system_id' => 30000142, 'system_name' => 'Jita',
            'region_id' => 10000002, 'region_name' => 'The Forge',
        ]);

        Transaction::where('character_id', $character->character_id)->delete();
        JournalEntry::where('character_id', $character->character_id)->delete();

        $id = 700000000;
        $journalId = 800000000;

        foreach ($types as $typeId => $name) {
            $buyPrice = random_int(5, 5000) + 0.5;

            for ($day = 25; $day >= 0; $day--) {
                $date = Carbon::now()->subDays($day);
                $qty = random_int(50, 500);

                // Buy.
                Transaction::create([
                    'transaction_id' => $id++, 'character_id' => $character->character_id,
                    'date' => $date->copy()->setTime(8, 0), 'type_id' => $typeId,
                    'location_id' => 60003760, 'quantity' => $qty, 'unit_price' => $buyPrice, 'is_buy' => true,
                ]);

                // Sell at a margin a couple of days later.
                $sellPrice = round($buyPrice * (1 + random_int(5, 30) / 100), 2);
                $sellTxnId = $id++;
                Transaction::create([
                    'transaction_id' => $sellTxnId, 'character_id' => $character->character_id,
                    'date' => $date->copy()->setTime(18, 0), 'type_id' => $typeId,
                    'location_id' => 60003760, 'quantity' => $qty, 'unit_price' => $sellPrice, 'is_buy' => false,
                ]);

                // Sales tax (~4.5%) linked to the sale, plus a broker fee.
                JournalEntry::create([
                    'id' => $journalId++, 'character_id' => $character->character_id,
                    'date' => $date->copy()->setTime(18, 0), 'ref_type' => 'transaction_tax',
                    'amount' => -round($sellPrice * $qty * 0.045, 2), 'context_id' => $sellTxnId,
                ]);
                JournalEntry::create([
                    'id' => $journalId++, 'character_id' => $character->character_id,
                    'date' => $date->copy()->setTime(8, 0), 'ref_type' => 'brokers_fee',
                    'amount' => -round($buyPrice * $qty * 0.015, 2),
                ]);
            }
        }

        $character->forceFill(['last_transaction_id' => $id, 'last_synced_at' => now()])->save();

        $matches = app(ProfitEngine::class)->rebuildForCharacter($character);
        $this->command->info("Demo data seeded: {$matches} trade matches for Demo Trader.");
    }
}
