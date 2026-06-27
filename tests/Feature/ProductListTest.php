<?php

namespace Tests\Feature;

use App\Livewire\ProductList;
use App\Models\Character;
use App\Models\EveType;
use App\Models\TradeMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductListTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_traded_items_and_filters_by_search(): void
    {
        $character = Character::create(['character_id' => 90000011, 'name' => 'Trader']);
        EveType::create(['type_id' => 34, 'name' => 'Tritanium']);
        EveType::create(['type_id' => 44992, 'name' => 'PLEX']);

        TradeMatch::insert([
            $this->match($character, 34, 100, 5, 8),
            $this->match($character, 44992, 1, 4_000_000, 5_000_000),
        ]);

        Livewire::test(ProductList::class)
            ->assertSee('Tritanium')
            ->assertSee('PLEX')
            ->set('search', 'PLEX')
            ->assertSee('PLEX')
            ->assertDontSee('Tritanium');
    }

    private function match(Character $c, int $typeId, int $qty, float $buy, float $sell): array
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
            'sell_date' => '2026-06-20 00:00:00',
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
