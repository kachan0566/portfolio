<?php

namespace Tests\Feature;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Services\Purchase\PurchaseOrderShowData;
use App\Support\PurchaseOrderType;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_product_show_displays_order_lines_receiving_summary_and_roll_detail(): void
    {
        $po = PurchaseOrder::query()->where('code', 'PO-2606-001')->first();
        $this->assertNotNull($po);

        $response = $this->get(route('purchases.show', $po->id));

        $response->assertOk();
        $response->assertSee('発注情報', false);
        $response->assertSee('発注内容', false);
        $response->assertSee('入荷状況', false);
        $response->assertSee('発注明細行', false);
        $response->assertSee('発注反数', false);
        $response->assertSee('入荷済反数', false);
        $response->assertSee('残反数', false);
        $response->assertSee('実測m', false);
        $response->assertDontSee('反明細（染め上がり実測）', false);
    }

    public function test_single_line_product_show_still_displays_order_lines_table(): void
    {
        $po = PurchaseOrder::query()->where('code', 'PO-2606-001')->first();
        $this->assertNotNull($po);
        $this->assertSame(1, $po->lines()->count());

        $response = $this->get(route('purchases.show', $po->id));

        $response->assertOk();
        $response->assertSee('発注内容', false);
    }

    public function test_multi_line_product_show_displays_all_skus(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-SHOW-001',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => 'ordered',
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => 6,
            'qty_tan' => 2,
            'qty_meters' => 100,
            'received_qty_tan' => 1,
            'received_qty_m' => 50,
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 2,
            'product_id' => 7,
            'qty_tan' => 1,
            'qty_meters' => 50,
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
        ]);

        $receiving = Receiving::query()->create([
            'code' => 'RCV-P-SHOW-001',
            'received_date' => '2026-06-28',
        ]);
        $receivingLine = ReceivingLine::query()->create([
            'receiving_id' => $receiving->id,
            'purchase_order_line_id' => $po->lines()->where('line_no', 1)->value('id'),
            'line_no' => 1,
            'qty_tan' => 1,
            'qty_m' => 50,
        ]);

        ProductRoll::query()->create([
            'code' => 'P-SHOW-ROLL-01',
            'product_id' => 6,
            'purchase_order_id' => $po->id,
            'receiving_line_id' => $receivingLine->id,
            'tan_qty' => 1,
            'actual_qty_m' => 49.5,
            'nominal_meters' => 50,
            'status' => ProductRoll::STATUS_IN_STOCK,
            'received_date' => '2026-06-28',
        ]);

        $response = $this->get(route('purchases.show', $po->id));

        $response->assertOk();
        $response->assertSee('P-SHOW-ROLL-01', false);
        $response->assertSee('49.5m', false);
        $response->assertSee('残反数', false);
    }

    public function test_show_data_service_builds_per_sku_receiving_summary(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-SHOW-002',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => 'ordered',
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => 6,
            'qty_tan' => 2,
            'qty_meters' => 100,
            'received_qty_tan' => 1,
            'received_qty_m' => 50,
        ]);

        $po->load(['lines.material', 'lines.greige', 'lines.product.greige']);
        $summary = PurchaseOrderShowData::receivingBySku($po);

        $this->assertCount(1, $summary);
        $this->assertSame(2.0, $summary[0]['ordered_tan']);
        $this->assertSame(1.0, $summary[0]['received_tan']);
        $this->assertSame(50, $summary[0]['received_m']);
        $this->assertSame(1.0, $summary[0]['remaining_tan']);
    }

    public function test_greige_show_displays_greige_sku_and_tan_columns(): void
    {
        $po = PurchaseOrder::query()->where('code', 'PO-G-2606-001')->first();
        $this->assertNotNull($po);

        $response = $this->get(route('purchases.show', $po->id));

        $response->assertOk();
        $response->assertSee('生機品番', false);
        $response->assertSee('発注反数', false);
        $response->assertDontSee('反明細（織り上がり実測）', false);
    }

    public function test_yarn_show_displays_kg_columns(): void
    {
        $po = PurchaseOrder::query()->where('type', PurchaseOrderType::YARN)->first();
        $this->assertNotNull($po);

        $response = $this->get(route('purchases.show', $po->id));

        $response->assertOk();
        $response->assertSee('糸品番', false);
        $response->assertSee('発注kg', false);
        $response->assertSee('入荷済kg', false);
    }

    public function test_yarn_received_detail_shows_receiving_lines(): void
    {
        $po = PurchaseOrder::query()->where('type', PurchaseOrderType::YARN)->first();
        $this->assertNotNull($po);

        $line = $po->lines()->first();
        $this->assertNotNull($line);

        $receiving = Receiving::query()->create([
            'code' => 'RCV-Y-SHOW-001',
            'received_date' => '2026-06-20',
        ]);

        ReceivingLine::query()->create([
            'receiving_id' => $receiving->id,
            'purchase_order_line_id' => $line->id,
            'line_no' => 1,
            'qty_kg' => 12.5,
        ]);

        $response = $this->get(route('purchases.show', $po->id));

        $response->assertOk();
        $response->assertSee('RCV-Y-SHOW-001', false);
        $response->assertSee('12.50 kg', false);
    }

    public function test_greige_received_detail_shows_roll_rows(): void
    {
        $po = PurchaseOrder::query()->where('code', 'PO-G-2606-001')->first();
        $this->assertNotNull($po);

        $line = $po->lines()->first();
        $this->assertNotNull($line);

        $receiving = Receiving::query()->create([
            'code' => 'RCV-G-SHOW-001',
            'received_date' => '2026-06-18',
        ]);
        $receivingLine = ReceivingLine::query()->create([
            'receiving_id' => $receiving->id,
            'purchase_order_line_id' => $line->id,
            'line_no' => 1,
            'qty_tan' => 1,
            'qty_m' => 99,
        ]);

        GreigeRoll::query()->create([
            'code' => 'G-SHOW-ROLL-01',
            'greige_id' => $line->greige_id,
            'purchase_order_id' => $po->id,
            'receiving_line_id' => $receivingLine->id,
            'tan_qty' => 1,
            'actual_qty_m' => 98.5,
            'nominal_meters' => 100,
            'status' => GreigeRoll::STATUS_IN_STOCK,
            'received_date' => '2026-06-18',
        ]);

        $response = $this->get(route('purchases.show', $po->id));

        $response->assertOk();
        $response->assertSee('G-SHOW-ROLL-01', false);
        $response->assertSee('98.5m', false);
    }
}
