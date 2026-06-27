<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\EveType;
use App\Models\Location;
use App\Services\EveSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EveSyncWatermarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_records_scalar_transaction_watermark_and_skips_known_on_resync(): void
    {
        $character = Character::create([
            'character_id' => 90000050,
            'name' => 'Watermark',
            'access_token' => 'token',
            'token_expires_at' => now()->addHour(),
        ]);

        // Pre-seed type + location so name resolution needs no extra ESI calls.
        EveType::create(['type_id' => 34, 'name' => 'Tritanium']);
        Location::create(['location_id' => 60003760, 'name' => 'Jita']);

        $transactions = [
            ['transaction_id' => 100, 'date' => '2026-06-25T00:00:00Z', 'type_id' => 34, 'location_id' => 60003760, 'quantity' => 10, 'unit_price' => 5.0, 'is_buy' => true],
            ['transaction_id' => 101, 'date' => '2026-06-26T00:00:00Z', 'type_id' => 34, 'location_id' => 60003760, 'quantity' => 10, 'unit_price' => 9.0, 'is_buy' => false],
        ];

        Http::fake([
            '*/wallet/transactions/*' => Http::response($transactions, 200),
            '*/wallet/journal/*' => Http::response([], 200, [
                'X-Pages' => '1',
                'Last-Modified' => 'Fri, 26 Jun 2026 12:00:00 GMT',
                'Expires' => 'Fri, 26 Jun 2026 13:00:00 GMT',
            ]),
        ]);

        $sync = app(EveSyncService::class);

        $first = $sync->syncCharacter($character);
        $this->assertSame(2, $first['transactions']);

        // The watermark must be the highest transaction id as an integer — the
        // bug stored an array here ('Array' in SQLite), breaking incremental sync.
        $character->refresh();
        $this->assertSame(101, (int) $character->last_transaction_id);
        $this->assertNotNull($character->wallet_next_sync_at);

        // A second sync sees only already-known transactions and collects none.
        $second = $sync->syncCharacter($character);
        $this->assertSame(0, $second['transactions']);
        $this->assertSame(101, (int) $character->refresh()->last_transaction_id);
    }
}
