<?php

namespace Tests\Feature;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Support\DemoData;
use App\Support\GreigeRoll as GreigeRollSupport;
use App\Support\ProductRoll as ProductRollSupport;
use App\Support\PurchaseOrderType;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        GreigeRollSupport::resetCacheForTesting();
        ProductRollSupport::resetCacheForTesting();
    }

    private function seedReceivings(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_receiving_seeder_matches_demo_base_rows(): void
    {
        $this->seedReceivings();

        $this->assertSame(DemoData::baseReceivingRows()->count(), Receiving::query()->count());
        $this->assertSame(5, ReceivingLine::query()->count());

        $receiving = Receiving::query()->where('code', 'RC-2606-001')->first();
        $this->assertNotNull($receiving);
        $this->assertSame('2026-06-08', $receiving->received_date?->toDateString());
    }

    public function test_receiving_seeder_creates_roll_records(): void
    {
        $this->seedReceivings();

        $this->assertGreaterThan(0, GreigeRoll::query()->count());
        $this->assertGreaterThan(0, ProductRoll::query()->count());

        $productRolls = ProductRoll::query()
            ->whereHas('receivingLine.receiving', fn ($q) => $q->where('code', 'RC-2606-001'))
            ->get();

        $this->assertGreaterThan(0, $productRolls->count());
        $this->assertSame(200, (int) round($productRolls->sum(fn ($roll) => (float) $roll->actual_qty_m)));

        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-001'))
            ->first();
        $this->assertNotNull($line);
        $this->assertSame(200, (int) $line->qty_m);
    }

    public function test_demo_data_receivings_reads_from_database_after_seed(): void
    {
        $this->seedReceivings();

        $receivings = DemoData::receivings();
        $this->assertSame(5, $receivings->count());
        $this->assertSame('RC-2606-005', $receivings->firstWhere('code', 'RC-2606-005')?->code);

        $yarnReceiving = $receivings->firstWhere('code', 'RC-2606-005');
        $this->assertSame(PurchaseOrderType::YARN, $yarnReceiving->po_type);
        $this->assertSame(150.0, (float) $yarnReceiving->qty_kg);

        $yarnLine = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-005'))
            ->first();
        $this->assertNotNull($yarnLine);
        $this->assertSame(150.0, (float) $yarnLine->qty_kg);
    }

    public function test_greige_roll_support_reads_from_database_after_seed(): void
    {
        $this->seedReceivings();

        $rolls = GreigeRollSupport::inStockForSku('KB-T');
        $this->assertGreaterThan(0, $rolls->count());
        $this->assertSame(200, (int) round($rolls->sum('actual_qty_m')));
    }

    public function test_receiving_index_page_loads_from_database(): void
    {
        $this->seedReceivings();

        $response = $this->get(route('receivings.index'));

        $response->assertOk();
        $response->assertSee('RC-2606-001');
        $response->assertSee('RC-2606-002');
        $response->assertSee('RC-2606-005');
    }
}
