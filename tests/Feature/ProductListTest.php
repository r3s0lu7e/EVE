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
            $this->match($character, 34, now()->subDay()->toDateTimeString()),
            $this->match($character, 44992, now()->subDay()->toDateTimeString()),
        ]);

        Livewire::test(ProductList::class)
            ->assertSee('Tritanium')
            ->assertSee('PLEX')
            ->set('search', 'PLEX')
            ->assertSee('PLEX')
            ->assertDontSee('Tritanium');
    }

    public function test_date_preset_filters_by_sell_date(): void
    {
        $character = Character::create(['character_id' => 90000012, 'name' => 'Trader']);
        EveType::create(['type_id' => 34, 'name' => 'Tritanium']);
        EveType::create(['type_id' => 35, 'name' => 'Pyerite']);

        TradeMatch::insert([
            $this->match($character, 34, now()->subDays(2)->toDateTimeString()),   // recent
            $this->match($character, 35, now()->subDays(60)->toDateTimeString()),  // older
        ]);

        Livewire::test(ProductList::class)
            // Default "all time" shows both.
            ->assertSee('Tritanium')
            ->assertSee('Pyerite')
            // Last 7 days drops the 60-day-old trade.
            ->call('setPreset', '7d')
            ->assertSee('Tritanium')
            ->assertDontSee('Pyerite');
    }

    public function test_filters_persist_across_instances(): void
    {
        Character::create(['character_id' => 90000013, 'name' => 'Trader']);

        // First instance changes filters; dehydrate writes them to the session.
        Livewire::test(ProductList::class)
            ->set('preset', '30d')
            ->set('sort', 'quantity');

        // A fresh instance (e.g. after navigating away and back) restores them.
        Livewire::test(ProductList::class)
            ->assertSet('preset', '30d')
            ->assertSet('sort', 'quantity');
    }

    private function match(Character $c, int $typeId, string $date): array
    {
        return [
            'character_id' => $c->character_id,
            'type_id' => $typeId,
            'location_id' => 60003760,
            'sell_transaction_id' => random_int(1, PHP_INT_MAX),
            'buy_transaction_id' => random_int(1, PHP_INT_MAX),
            'quantity' => 100,
            'buy_unit_cost' => 5,
            'sell_unit_price' => 8,
            'sell_date' => $date,
            'gross_profit' => 300,
            'sales_tax_alloc' => 0,
            'broker_fee_alloc' => 0,
            'net_profit' => 300,
            'unmatched' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
