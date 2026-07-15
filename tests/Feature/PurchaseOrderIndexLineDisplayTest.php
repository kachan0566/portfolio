<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderIndexLineDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
    }

    public function test_index_shows_each_line_for_multi_line_purchase(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-INDEX-001',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => 'ordered',
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-01',
            'due_date' => '2026-06-30',
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => 6,
            'qty_tan' => 2,
            'qty_meters' => 100,
            'stage' => PurchaseOrderStages::PRODUCT_DYEING,
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 2,
            'product_id' => 7,
            'qty_tan' => 1,
            'qty_meters' => 50,
            'stage' => PurchaseOrderStages::PRODUCT_DYEING,
        ]);

        $response = $this->get(route('purchases.index'));

        $response->assertOk();
        $response->assertSee('PO-P-INDEX-001', false);
        $response->assertSee('name="expected_arrival_date"', false);
        $response->assertSee('name="arrival_memo"', false);
    }

    public function test_index_shows_per_line_stage_when_only_one_line_received(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-INDEX-002',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => 'partial',
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-01',
            'due_date' => '2026-06-30',
        ]);

        $line1 = PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => 6,
            'qty_tan' => 2,
            'qty_meters' => 100,
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
            'stage' => PurchaseOrderStages::PRODUCT_DYEING,
        ]);
        $line2 = PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 2,
            'product_id' => 7,
            'qty_tan' => 1,
            'qty_meters' => 50,
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
            'stage' => PurchaseOrderStages::PRODUCT_DYEING,
        ]);

        $qty = 20;
        ReceivingRegistrar::register($po->id, '2026-07-10', PurchaseOrderType::PRODUCT, [
            [
                'purchase_order_line_id' => $line1->id,
                'qty_tan' => QtyHelper::tanCount($qty, 6),
                'qty_meters' => $qty,
                'roll_lines' => [['tan_qty' => QtyHelper::tanCount($qty, 6), 'actual_qty_m' => (float) $qty]],
            ],
        ]);

        $response = $this->get(route('purchases.index'));

        $response->assertOk();
        $response->assertSee(PurchaseOrderStages::PRODUCT_IN_STOCK.PurchaseOrderStages::PARTIAL_SUFFIX, false);
        $response->assertSee(PurchaseOrderStages::PRODUCT_DYEING, false);
    }

    public function test_index_filters_by_line_sku(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-INDEX-003',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => 'ordered',
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-01',
            'due_date' => '2026-06-30',
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => 6,
            'qty_meters' => 100,
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 2,
            'product_id' => 7,
            'qty_meters' => 50,
        ]);

        $product6Sku = \App\Models\Product::query()->find(6)?->sku;
        $this->assertNotNull($product6Sku);

        $response = $this->get(route('purchases.index', ['sku' => $product6Sku]));

        $response->assertOk();
        $response->assertSee('PO-P-INDEX-003', false);
        $response->assertSee($product6Sku, false);
    }

    public function test_index_shows_purchase_code_link(): void
    {
        $response = $this->get(route('purchases.index'));

        $response->assertOk();
        $response->assertSee('PO-2606-001', false);
        $response->assertSee('発注一覧', false);
        $response->assertSee(route('purchases.show', 1, false), false);
    }

    public function test_update_saves_per_line_manual_stage(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-INDEX-004',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => 'ordered',
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-01',
            'due_date' => '2026-06-30',
        ]);

        $line1 = PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'product_id' => 6,
            'qty_meters' => 100,
        ]);
        $line2 = PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 2,
            'product_id' => 7,
            'qty_meters' => 50,
            'stage' => PurchaseOrderStages::PRODUCT_DYEING,
        ]);

        $response = $this->put(route('purchases.update', $po->id), [
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-01',
            'due_date' => '2026-06-30',
            'status' => 'ordered',
            'arrival_memo' => '',
            'line_stages' => [
                (string) $line2->id => PurchaseOrderStages::PRODUCT_DYEING,
            ],
        ]);

        $response->assertRedirect(route('purchases.show', $po->id));
        $line2->refresh();
        $this->assertSame(PurchaseOrderStages::PRODUCT_DYEING, $line2->stage);

        $index = $this->get(route('purchases.index'));
        $index->assertSee(PurchaseOrderStages::PRODUCT_DYEING, false);
        $this->assertNotNull($line1->fresh());
    }
}
