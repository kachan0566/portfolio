<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\ReceivingLine;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\GreigeInventory;
use App\Support\ProductStock;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use App\Support\YarnInventory;
use App\Support\YarnMovementType;
use Database\Seeders\CostFoundationSeeder;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    private function seedBase(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(CostFoundationSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_receiving_index_shows_three_types(): void
    {
        $response = $this->get(route('receivings.index'));

        $response->assertOk();
        $response->assertSee('糸発注');
        $response->assertSee('生機発注');
        $response->assertSee('RC-2606-005');
        $response->assertSee('RC-2606-002');
    }

    public function test_yarn_receiving_registers_movements_and_po_line(): void
    {
        $before = YarnInventory::effectiveStockKg(1);

        $result = ReceivingRegistrar::register(
            10,
            '2026-06-26',
            PurchaseOrderType::YARN,
            qtyKg: 100.0,
        );

        // 織工場入荷は入庫と消費が同量で相殺され、在庫合計は変わらない
        $this->assertSame($before, YarnInventory::effectiveStockKg(1));

        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', $result['code']))
            ->first();
        $this->assertNotNull($line);
        $this->assertSame(100.0, (float) $line->qty_kg);

        $this->assertDatabaseHas('yarn_stock_movements', [
            'material_id' => 1,
            'movement_type' => YarnMovementType::RECEIVING,
            'qty_kg' => 100.0,
        ]);
        $this->assertDatabaseHas('yarn_stock_movements', [
            'material_id' => 1,
            'movement_type' => YarnMovementType::CONSUMPTION,
            'qty_kg' => -100.0,
        ]);
    }

    public function test_greige_receiving_shows_in_inventory(): void
    {
        $before = GreigeInventory::totalMetersForSku('KB-A');
        $remaining = (int) floor(PurchaseOrder::remainingQtyFor(4));
        $qty = min(150, max(1, $remaining));

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::GREIGE,
            'po_id' => 4,
            'qty_tan' => QtyHelper::tanCount($qty, null, true, 'KB-A'),
            'qty_meters' => $qty,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + $qty, GreigeInventory::totalMetersForSku('KB-A'));
    }

    /**
     * 0.5反＝25mの製品入荷で在庫が増えることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_product_receiving_increases_stock(): void
    {
        $before = ProductStock::effectiveStock(7);
        $qty = 25;

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'po_id' => 9,
            'qty_tan' => QtyHelper::tanCount($qty, 7),
            'qty_meters' => $qty,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + $qty, ProductStock::effectiveStock(7));
    }

    /**
     * 複数明細形式でも0.5反＝25mの製品入荷で在庫が増えることを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_product_receiving_via_multi_line_entries_increases_stock(): void
    {
        $before = ProductStock::effectiveStock(7);
        $poLineId = (int) PurchaseOrder::query()->with('lines')->find(9)?->lines->first()?->id;
        $this->assertGreaterThan(0, $poLineId);

        $qty = 25;
        $qtyTan = QtyHelper::tanCount($qty, 7);

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'po_id' => 9,
            'date' => '2026-06-26',
            'entries' => [
                [
                    'selected' => '1',
                    'po_line_id' => $poLineId,
                    'qty_tan' => $qtyTan,
                    'qty_meters' => $qty,
                    'rolls' => [
                        ['tan_qty' => $qtyTan, 'actual_qty_m' => (float) $qty],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + $qty, ProductStock::effectiveStock(7));
    }

    /**
     * 0.25の倍数ではない0.3反を入荷時に丸めず、保存前に拒否することを確認する。
     *
     * @return void PHPUnit が成功／失敗を判定するため戻り値は使わない
     */
    public function test_product_receiving_rejects_tan_outside_quarter_step_without_rounding(): void
    {
        $before = ReceivingLine::query()->count();

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'po_id' => 9,
            'qty_tan' => 0.3,
            'qty_meters' => 15,
            'date' => '2026-06-26',
        ]);

        $response->assertRedirect(route('receivings.create', ['type' => PurchaseOrderType::PRODUCT]));
        $response->assertSessionHas('error', '入荷する明細行を1行以上選択し、数量を正しく入力してください。');
        $this->assertSame($before, ReceivingLine::query()->count());
    }

    public function test_greige_receiving_via_multi_line_entries_shows_in_inventory(): void
    {
        $before = GreigeInventory::totalMetersForSku('KB-A');
        $poLineId = (int) PurchaseOrder::query()->with('lines')->find(4)?->lines->first()?->id;
        $this->assertGreaterThan(0, $poLineId);

        $remaining = (int) floor(PurchaseOrder::remainingQtyFor(4));
        $qty = min(150, max(1, $remaining));
        $qtyTan = QtyHelper::tanCount($qty, null, true, 'KB-A');

        $response = $this->post(route('receivings.store'), [
            'type' => PurchaseOrderType::GREIGE,
            'po_id' => 4,
            'date' => '2026-06-26',
            'entries' => [
                [
                    'selected' => '1',
                    'po_line_id' => $poLineId,
                    'qty_tan' => $qtyTan,
                    'qty_meters' => $qty,
                    'rolls' => [
                        ['tan_qty' => $qtyTan, 'actual_qty_m' => (float) $qty],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('receivings.index'));
        $this->assertSame($before + $qty, GreigeInventory::totalMetersForSku('KB-A'));
    }
}
