<?php

namespace Tests\Feature;

use App\Livewire\Assets;
use App\Models\Character;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_net_worth_total_and_per_item_breakdown(): void
    {
        $character = Character::create(['character_id' => 90000021, 'name' => 'Hoarder']);
        session(['active_character_id' => $character->character_id]);
        $this->mockAssets();

        Livewire::test(Assets::class)
            ->assertSee('Total net worth')
            ->assertSee('19,000.00') // assets 11,000 + sell 2,000 + escrow 1,000 + wallet 5,000
            ->assertSee('5,000.00')  // wallet breakdown
            ->assertSee('Tritanium')
            ->assertSee('PLEX');
    }

    public function test_search_filters_the_breakdown_but_not_the_total(): void
    {
        $character = Character::create(['character_id' => 90000022, 'name' => 'Hoarder']);
        session(['active_character_id' => $character->character_id]);
        $this->mockAssets();

        // Target the type-icon URL, which only appears in the table rows.
        Livewire::test(Assets::class)
            ->set('search', 'PLEX')
            ->call('applyFilters')
            ->assertSee('types/44992/icon')   // PLEX row present
            ->assertDontSee('types/34/icon')  // Tritanium row filtered out
            // Total stays the full net worth, not just the filtered row.
            ->assertSee('19,000.00');
    }

    public function test_basis_toggle_switches_price_basis(): void
    {
        $character = Character::create(['character_id' => 90000023, 'name' => 'Hoarder']);
        session(['active_character_id' => $character->character_id]);
        $this->mockAssets();

        Livewire::test(Assets::class)
            ->assertSet('basis', 'sell')
            ->call('setBasis', 'buy')
            ->assertSet('basis', 'buy');
    }

    public function test_prompts_login_without_a_character(): void
    {
        Livewire::test(Assets::class)
            ->assertSee('Log in with EVE to value your assets.');
    }

    private function mockAssets(): void
    {
        $this->mock(AssetService::class, function ($mock) {
            $mock->shouldReceive('valuation')->andReturn($this->valuation());
            $mock->shouldReceive('history')->andReturn([]);
        });
    }

    /** @return array<string,mixed> */
    private function valuation(): array
    {
        return [
            'rows' => [
                ['type_id' => 34, 'name' => 'Tritanium', 'group_name' => 'Mineral', 'quantity' => 1000, 'unit_price' => 10.0, 'value' => 10000.0],
                ['type_id' => 44992, 'name' => 'PLEX', 'group_name' => 'Token', 'quantity' => 1, 'unit_price' => 1000.0, 'value' => 1000.0],
            ],
            'assets_value' => 11000.0,
            'sell_orders' => 2000.0,
            'escrow' => 1000.0,
            'wallet' => 5000.0,
            'total' => 19000.0,
            'items' => 1001,
            'types' => 2,
            'unpriced' => 0,
        ];
    }
}
