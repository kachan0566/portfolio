<?php

namespace Tests\Feature;

use App\Support\DemoData;
use App\Support\DemoOverlay;
use Tests\TestCase;

class YarnPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DemoOverlay::clear();
    }

    public function test_index_shows_yarn_price_screen(): void
    {
        $response = $this->get(route('prices.index'));

        $response->assertOk();
        $response->assertSee('月別糸価格');
        $response->assertSee('綿糸');
        $response->assertSee('ポリエステル糸');
        $response->assertDontSee('染料');
        $response->assertDontSee('仕上げ剤');
    }

    public function test_store_validates_and_persists_yarn_price_in_session_overlay(): void
    {
        $response = $this->post(route('prices.store'), [
            'material_id' => 1,
            'ym' => '2026-07',
            'price' => 600,
        ]);

        $response->assertRedirect(route('prices.index'));
        $this->assertSame(600, DemoData::yarnPrice(1, '2026-07'));

        $index = $this->get(route('prices.index'));
        $index->assertSee('2026-07');
        $index->assertSee('600');
    }

    public function test_store_rejects_duplicate_yarn_price(): void
    {
        $response = $this->post(route('prices.store'), [
            'material_id' => 1,
            'ym' => '2026-06',
            'price' => 999,
        ]);

        $response->assertSessionHasErrors('ym');
    }

    public function test_update_changes_existing_yarn_price(): void
    {
        $row = DemoData::materialPrices()->firstWhere('ym', '2026-06');

        $response = $this->put(route('prices.update', $row->id), [
            'price' => 580,
        ]);

        $response->assertRedirect(route('prices.index'));
        $this->assertSame(580, DemoData::yarnPrice($row->material_id, $row->ym));
    }
}
