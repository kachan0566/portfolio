<?php

namespace Tests\Feature;

use App\Models\ReceivingLine;
use App\Models\YarnStockMovement;
use App\Services\Yarn\YarnStockMovementRecorder;
use App\Support\YarnMovementReference;
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

class YarnStockMovementTest extends TestCase
{
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(CostFoundationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_yarn_receiving_creates_receiving_and_consumption_pair(): void
    {
        $this->seedBase();

        $line = ReceivingLine::query()->whereHas(
            'purchaseOrderLine.purchaseOrder',
            fn ($q) => $q->where('code', 'PO-Y-2606-002'),
        )->first();

        $this->assertNotNull($line);

        $receiving = YarnStockMovement::query()
            ->where('reference_type', YarnMovementReference::RECEIVING_LINE)
            ->where('reference_id', $line->id)
            ->where('movement_type', YarnMovementType::RECEIVING)
            ->first();

        $consumption = YarnStockMovement::query()
            ->where('reference_type', YarnMovementReference::RECEIVING_LINE)
            ->where('reference_id', $line->id)
            ->where('movement_type', YarnMovementType::CONSUMPTION)
            ->first();

        $this->assertNotNull($receiving);
        $this->assertNotNull($consumption);
        $this->assertSame(150.0, (float) $receiving->qty_kg);
        $this->assertSame(-150.0, (float) $consumption->qty_kg);
    }

    public function test_record_yarn_receiving_is_idempotent(): void
    {
        $this->seedBase();

        $line = ReceivingLine::query()->firstOrFail();

        YarnStockMovementRecorder::recordYarnReceiving(
            $line,
            1,
            10.0,
            '2026-06-01',
            'retry',
        );

        $count = YarnStockMovement::query()
            ->where('reference_type', YarnMovementReference::RECEIVING_LINE)
            ->where('reference_id', $line->id)
            ->count();

        $this->assertSame(2, $count);
    }
}
