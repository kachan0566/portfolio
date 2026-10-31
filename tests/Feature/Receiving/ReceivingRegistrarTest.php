<?php

namespace Tests\Feature\Receiving;

use App\Models\Inventory\GreigeRoll;
use App\Models\Inventory\ProductRoll;
use App\Models\Purchase\PurchaseOrderLine;
use App\Models\Receiving\Receiving;
use App\Models\Receiving\ReceivingLine;
use App\Services\Inventory\ShipmentRollAllocator;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\Core\DemoData;
use App\Support\Inventory\ProductStock;
use App\Models\Purchase\PurchaseOrder;
use App\Support\Purchase\PurchaseOrderType;
use App\Support\Qty\QtyHelper;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_product_receiving_registers_to_database(): void
    {
        $this->seedBase();

        $beforeStock = ProductStock::effectiveStock(6);
        $remaining = (int) floor(PurchaseOrder::remainingQtyFor(7));
        $qty = min(20, max(1, $remaining));

        $result = ReceivingRegistrar::register(
            7,
            '2026-07-01',
            PurchaseOrderType::PRODUCT,
            qtyTan: QtyHelper::tanCount($qty, 6),
            qtyMeters: $qty,
            rollLines: [['tan_qty' => QtyHelper::tanCount($qty, 6), 'actual_qty_m' => (float) $qty]],
        );

        $this->assertNotEmpty($result['code']);
        $this->assertSame($beforeStock + $qty, ProductStock::effectiveStock(6));

        $receiving = Receiving::query()->where('code', $result['code'])->first();
        $this->assertNotNull($receiving);

        $line = ReceivingLine::query()->where('receiving_id', $receiving->id)->first();
        $this->assertNotNull($line);
        $this->assertSame($qty, (int) $line->qty_m);

        $poLine = PurchaseOrderLine::query()->where('purchase_order_id', 7)->where('line_no', 1)->first();
        $this->assertGreaterThanOrEqual($qty, (int) $poLine->received_qty_m);
    }

    public function test_new_product_rolls_are_available_for_fifo_preview(): void
    {
        $this->seedBase();

        $qty = 10;
        ReceivingRegistrar::register(
            7,
            '2026-07-02',
            PurchaseOrderType::PRODUCT,
            qtyTan: QtyHelper::tanCount($qty, 6),
            qtyMeters: $qty,
            rollLines: [['tan_qty' => QtyHelper::tanCount($qty, 6), 'actual_qty_m' => (float) $qty]],
        );

        $preview = ShipmentRollAllocator::previewFifo(6, QtyHelper::tanCount($qty, 6));
        $this->assertGreaterThan(0, count($preview));
    }

    public function test_yarn_receiving_sets_line_qty_kg(): void
    {
        $this->seedBase();

        $result = ReceivingRegistrar::register(
            10,
            '2026-07-03',
            PurchaseOrderType::YARN,
            qtyKg: 50.0,
        );

        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', $result['code']))
            ->first();

        $this->assertNotNull($line);
        $this->assertSame(50.0, (float) $line->qty_kg);
    }

    /**
     * 同じ製品発注へ2回入荷しても、反コードの連番が 01, 02 と続き重複しないことを確認する。
     *
     * @return void
     */
    public function test_second_product_receiving_on_same_po_uses_next_roll_code(): void
    {
        $this->seedBase();

        $qty = 25;
        $qtyTan = QtyHelper::tanCount($qty, 7);
        $rollLine = ['tan_qty' => $qtyTan, 'actual_qty_m' => (float) $qty];

        ReceivingRegistrar::register(
            9,
            '2026-07-04',
            PurchaseOrderType::PRODUCT,
            qtyTan: $qtyTan,
            qtyMeters: $qty,
            rollLines: [$rollLine],
        );
        ReceivingRegistrar::register(
            9,
            '2026-07-05',
            PurchaseOrderType::PRODUCT,
            qtyTan: $qtyTan,
            qtyMeters: $qty,
            rollLines: [$rollLine],
        );

        $codes = ProductRoll::query()
            ->where('purchase_order_id', 9)
            ->where('product_id', 7)
            ->orderBy('id')
            ->pluck('code')
            ->all();

        $this->assertContains('FAB-T-BK-PO-2606-009-01', $codes);
        $this->assertContains('FAB-T-BK-PO-2606-009-02', $codes);
        $this->assertCount(2, $codes);
    }

    /**
     * 同じ生機発注へ2回入荷しても、反コードの連番が 01, 02 と続き重複しないことを確認する。
     *
     * @return void
     */
    public function test_second_greige_receiving_on_same_po_uses_next_roll_code(): void
    {
        $this->seedBase();

        $qty = 50;
        $qtyTan = QtyHelper::tanCount($qty, null, true, 'KB-A');
        $rollLine = ['tan_qty' => $qtyTan, 'actual_qty_m' => (float) $qty];

        ReceivingRegistrar::register(
            4,
            '2026-07-04',
            PurchaseOrderType::GREIGE,
            qtyTan: $qtyTan,
            qtyMeters: $qty,
            rollLines: [$rollLine],
        );
        ReceivingRegistrar::register(
            4,
            '2026-07-05',
            PurchaseOrderType::GREIGE,
            qtyTan: $qtyTan,
            qtyMeters: $qty,
            rollLines: [$rollLine],
        );

        $codes = GreigeRoll::query()
            ->where('purchase_order_id', 4)
            ->orderBy('id')
            ->pluck('code')
            ->all();

        $this->assertContains('KB-A-PO-G-2606-001-01', $codes);
        $this->assertContains('KB-A-PO-G-2606-001-02', $codes);
        $this->assertCount(2, $codes);
    }
}
