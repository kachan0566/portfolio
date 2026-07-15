<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\DemoState;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiLinePurchaseReceivingTest extends TestCase
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

    public function test_store_product_purchase_with_multiple_lines(): void
    {
        $response = $this->post(route('purchases.store'), [
            'type' => PurchaseOrderType::PRODUCT,
            'supplier_id' => 6,
            'ship_to_id' => 4,
            'order_date' => '2026-06-25',
            'due_date' => '2026-07-01',
            'save_action' => 'draft',
            'lines' => [
                ['product_id' => 6, 'qty_meters' => 100],
                ['product_id' => 6, 'qty_meters' => 50],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $po = PurchaseOrder::query()->where('code', 'like', 'PO-P-%')->orderByDesc('id')->first();
        $this->assertNotNull($po);
        $this->assertSame(2, $po->lines()->count());
        $this->assertSame(150, (int) $po->lines->sum('qty_meters'));
    }

    public function test_multi_line_receiving_creates_multiple_receiving_lines(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-TEST-001',
            'type' => PurchaseOrderType::PRODUCT,
            'status' => 'ordered',
            'supplier_id' => 1,
            'ship_to_id' => 3,
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
        ]);
        $line2 = PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 2,
            'product_id' => 7,
            'qty_tan' => 1,
            'qty_meters' => 50,
            'received_qty_tan' => 0,
            'received_qty_m' => 0,
        ]);

        $qty1 = 20;
        $qty2 = 10;

        $result = ReceivingRegistrar::register($po->id, '2026-07-10', PurchaseOrderType::PRODUCT, [
            [
                'purchase_order_line_id' => $line1->id,
                'qty_tan' => QtyHelper::tanCount($qty1, 6),
                'qty_meters' => $qty1,
                'roll_lines' => [['tan_qty' => QtyHelper::tanCount($qty1, 6), 'actual_qty_m' => (float) $qty1]],
            ],
            [
                'purchase_order_line_id' => $line2->id,
                'qty_tan' => QtyHelper::tanCount($qty2, 7),
                'qty_meters' => $qty2,
                'roll_lines' => [['tan_qty' => QtyHelper::tanCount($qty2, 7), 'actual_qty_m' => (float) $qty2]],
            ],
        ]);

        $receiving = Receiving::query()->where('code', $result['code'])->first();
        $this->assertNotNull($receiving);
        $this->assertSame(2, $receiving->lines()->count());

        $displayRows = $receiving->toDisplayObjects();
        $this->assertCount(2, $displayRows);
        $this->assertSame(1, $displayRows[0]->line_no);
        $this->assertSame(2, $displayRows[1]->line_no);

        $line1->refresh();
        $line2->refresh();
        $this->assertSame($qty1, (int) $line1->received_qty_m);
        $this->assertSame($qty2, (int) $line2->received_qty_m);
    }

    public function test_po_line_remaining_returns_per_line_balance(): void
    {
        $line = PurchaseOrderLine::query()
            ->with('purchaseOrder')
            ->get()
            ->first(fn (PurchaseOrderLine $row) => DemoState::poLineRemaining((int) $row->id) > 0);

        $this->assertNotNull($line, '入荷残のある明細行が必要です');

        $remaining = DemoState::poLineRemaining((int) $line->id);
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual($line->orderedQty(), $remaining + $line->receivedQty());
    }

    public function test_receiving_index_shows_line_numbers_for_multi_line_receiving(): void
    {
        $po = PurchaseOrder::query()->create([
            'code' => 'PO-P-TEST-002',
            'type' => PurchaseOrderType::YARN,
            'status' => 'ordered',
            'supplier_id' => 2,
            'ship_to_id' => 1,
            'order_date' => '2026-06-01',
            'due_date' => '2026-06-30',
        ]);

        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'material_id' => 1,
            'qty_kg' => 100,
            'received_qty_kg' => 0,
        ]);
        PurchaseOrderLine::query()->create([
            'purchase_order_id' => $po->id,
            'line_no' => 2,
            'material_id' => 2,
            'qty_kg' => 50,
            'received_qty_kg' => 0,
        ]);

        $lines = $po->fresh('lines')->lines;
        ReceivingRegistrar::register($po->id, '2026-07-11', PurchaseOrderType::YARN, [
            ['purchase_order_line_id' => $lines[0]->id, 'qty_kg' => 10.0],
            ['purchase_order_line_id' => $lines[1]->id, 'qty_kg' => 5.0],
        ]);

        $response = $this->get(route('receivings.index'));
        $response->assertOk();
        $response->assertSee('1/2', false);
        $response->assertSee('2/2', false);
    }
}
