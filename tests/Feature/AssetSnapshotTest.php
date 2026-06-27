<?php

namespace Tests\Feature;

use App\Models\AssetSnapshot;
use App\Models\Character;
use App\Services\AssetService;
use App\Services\EsiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssetSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_valuation_includes_orders_and_records_a_tracker_snapshot(): void
    {
        $character = Character::create(['character_id' => 90000031, 'name' => 'Hoarder']);

        $this->fakeEsi(
            walletBalance: 1000.0,
            assets: [['type_id' => 34, 'quantity' => 100]],
            orders: [
                ['is_buy_order' => false, 'price' => 6.0, 'volume_remain' => 50],  // sell: 300
                ['is_buy_order' => true, 'escrow' => 200.0],                        // escrow: 200
            ],
        );

        $result = app(AssetService::class)->valuation($character, 'sell');

        // 100×sell(5)=500 assets + 1000 wallet + 300 sell orders + 200 escrow = 2000.
        $this->assertSame(500.0, $result['assets_value']);
        $this->assertSame(300.0, $result['sell_orders']);
        $this->assertSame(200.0, $result['escrow']);
        $this->assertSame(1000.0, $result['wallet']);
        $this->assertSame(2000.0, $result['total']);

        $snapshot = AssetSnapshot::where('character_id', $character->character_id)->sole();
        $this->assertSame(2000.0, $snapshot->total);
        $this->assertSame(300.0, $snapshot->sell_orders);
        $this->assertSame(200.0, $snapshot->escrow);

        $history = app(AssetService::class)->history($character);
        $this->assertCount(1, $history);
        $this->assertSame(2000.0, $history[0]['total']);
    }

    public function test_does_not_record_a_duplicate_when_value_is_unchanged(): void
    {
        $character = Character::create(['character_id' => 90000032, 'name' => 'Hoarder']);

        $this->fakeEsi(
            walletBalance: 1000.0,
            assets: [['type_id' => 34, 'quantity' => 100]],
            orders: [],
        );

        $service = app(AssetService::class);
        $service->valuation($character, 'sell');
        $service->valuation($character, 'sell'); // same value → no new point

        $this->assertSame(1, AssetSnapshot::where('character_id', $character->character_id)->count());
    }

    /**
     * @param  array<int,array<string,mixed>>  $assets
     * @param  array<int,array<string,mixed>>  $orders
     */
    private function fakeEsi(float $walletBalance, array $assets, array $orders): void
    {
        $esi = \Mockery::mock(EsiClient::class);
        $esi->shouldReceive('walletBalance')->andReturn($walletBalance);
        $esi->shouldReceive('assets')->andReturn($assets);
        $esi->shouldReceive('characterOrders')->andReturn($orders);
        $esi->shouldReceive('resolveNames')->andReturn([
            ['id' => 34, 'name' => 'Tritanium', 'category' => 'inventory_type'],
        ]);
        $this->app->instance(EsiClient::class, $esi);

        // Fuzzwork bulk prices: buy 4, sell 5 for Tritanium.
        Http::fake([
            'market.fuzzwork.co.uk/*' => Http::response([
                '34' => [
                    'buy' => ['percentile' => '4.0'],
                    'sell' => ['percentile' => '5.0'],
                ],
            ]),
        ]);
    }
}
