<?php

namespace Tests\Feature;

use App\Models\ProductRoll;
use App\Models\PurchaseOrderLine;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Services\Inventory\ShipmentRollAllocator;
use App\Services\Receiving\ReceivingRegistrar;
use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\ProductRoll as ProductRollSupport;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
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

    protected function setUp(): void
    {
        parent::setUp();
        ProductRollSupport::resetCacheForTesting();
    }

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

        $beforeStock = DemoState::effectiveStock(6);
        $remaining = (int) floor(DemoState::poRemaining(7));
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
        $this->assertSame($beforeStock + $qty, DemoState::effectiveStock(6));

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
}
