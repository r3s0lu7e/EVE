<?php

namespace Tests\Feature;

use App\Livewire\ProductDetail;
use App\Models\Character;
use App\Models\EveType;
use App\Models\Location;
use App\Models\TradeMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_shows_local_stats_and_live_market(): void
    {
        $character = Character::create(['character_id' => 90000009, 'name' => 'Trader']);
        EveType::create(['type_id' => 34, 'name' => 'Tritanium', 'group_name' => 'Mineral']);
        Location::create(['location_id' => 60003760, 'name' => 'Jita IV - Moon 4', 'region_id' => 10000002]);

        TradeMatch::insert([
            $this->match($character, 100, 5, 8, '2026-06-20 00:00:00'),  // buy 5, sell 8
            $this->match($character, 50, 4, 9, '2026-06-21 00:00:00'),   // buy 4 (min), sell 9 (max)
        ]);

        Http::fake([
            '*/markets/10000002/orders/*' => Http::response([
                ['is_buy_order' => false, 'price' => 6.0, 'volume_remain' => 1000],
                ['is_buy_order' => true, 'price' => 5.5, 'volume_remain' => 2000],
            ], 200, ['X-Pages' => '1']),
            '*/markets/10000002/history/*' => Http::response([
                ['date' => '2026-06-24', 'average' => 5.9, 'highest' => 6.1, 'lowest' => 5.7, 'order_count' => 10, 'volume' => 5000],
                ['date' => '2026-06-25', 'average' => 6.0, 'highest' => 6.2, 'lowest' => 5.8, 'order_count' => 12, 'volume' => 6000],
            ], 200),
            '*/universe/types/34/*' => Http::response([
                'type_id' => 34, 'description' => 'The most basic mineral.', 'volume' => 0.01, 'group_id' => 18,
            ], 200),
        ]);

        Livewire::test(ProductDetail::class, ['typeId' => 34])
            ->assertSee('Tritanium')
            ->assertSee('Mineral')
            // Local realized net profit: (8-5)*100 + (9-4)*50 = 550.
            ->assertSee('550.00')
            // Min–max buy and sell price ranges.
            ->assertSee('4.00 – 5.00')
            ->assertSee('8.00 – 9.00')
            // Live market best sell (6.00) derived from the faked orders.
            ->assertSee('6.00')
            // ESI type description.
            ->assertSee('The most basic mineral.');
    }

    private function match(Character $c, int $qty, float $buy, float $sell, string $date): array
    {
        $gross = ($sell - $buy) * $qty;

        return [
            'character_id' => $c->character_id,
            'type_id' => 34,
            'location_id' => 60003760,
            'sell_transaction_id' => random_int(1, PHP_INT_MAX),
            'buy_transaction_id' => random_int(1, PHP_INT_MAX),
            'quantity' => $qty,
            'buy_unit_cost' => $buy,
            'sell_unit_price' => $sell,
            'sell_date' => $date,
            'gross_profit' => $gross,
            'sales_tax_alloc' => 0,
            'broker_fee_alloc' => 0,
            'net_profit' => $gross,
            'unmatched' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
