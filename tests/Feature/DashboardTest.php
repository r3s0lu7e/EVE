<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Character;
use App\Models\EveType;
use App\Models\TradeMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_for_guest(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('Log in with EVE');
    }

    public function test_dashboard_summarizes_matches_and_filters_by_product(): void
    {
        $character = Character::create(['character_id' => 90000003, 'name' => 'Trader']);
        EveType::create(['type_id' => 34, 'name' => 'Tritanium']);
        EveType::create(['type_id' => 35, 'name' => 'Pyerite']);

        TradeMatch::insert([
            $this->match($character, 34, 100, 5, 8, '2026-06-20 00:00:00'),  // net 300
            $this->match($character, 35, 10, 50, 80, '2026-06-21 00:00:00'), // net 300
        ]);

        Livewire::test(Dashboard::class, ['character' => (string) $character->character_id])
            ->set('from', '2026-06-01')
            ->set('to', '2026-06-30')
            ->assertSee('Tritanium')
            ->assertSee('Pyerite')
            // Filter to a single product.
            ->set('search', 'Trit')
            ->assertSee('Tritanium')
            ->assertDontSee('Pyerite');
    }

    private function match(Character $c, int $typeId, int $qty, float $buy, float $sell, string $date): array
    {
        $gross = ($sell - $buy) * $qty;

        return [
            'character_id' => $c->character_id,
            'type_id' => $typeId,
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
