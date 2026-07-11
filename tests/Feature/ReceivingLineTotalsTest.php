<?php

namespace Tests\Feature;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\ReceivingLine;
use App\Services\Receiving\ReceivingLineTotals;
use Database\Seeders\MasterCatalogSeeder;
use Database\Seeders\MasterFoundationSeeder;
use Database\Seeders\OrderAllocationSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\ReceivingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingLineTotalsTest extends TestCase
{
    use RefreshDatabase;

    private function seedReceivings(): void
    {
        $this->seed(MasterFoundationSeeder::class);
        $this->seed(MasterCatalogSeeder::class);
        $this->seed(OrderSeeder::class);
        $this->seed(PurchaseOrderSeeder::class);
        $this->seed(OrderAllocationSeeder::class);
        $this->seed(ReceivingSeeder::class);
    }

    public function test_sync_sets_product_line_qty_from_rolls(): void
    {
        $this->seedReceivings();

        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-001'))
            ->first();

        $this->assertNotNull($line);
        $rolls = ProductRoll::query()->where('receiving_line_id', $line->id)->get();
        $this->assertGreaterThan(0, $rolls->count());
        $this->assertSame(
            (int) round((float) $rolls->sum(fn ($roll) => (float) $roll->actual_qty_m)),
            (int) $line->qty_m,
        );
    }

    public function test_sync_sets_greige_line_qty_from_rolls(): void
    {
        $this->seedReceivings();

        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-002'))
            ->first();

        $this->assertNotNull($line);
        $rolls = GreigeRoll::query()->where('receiving_line_id', $line->id)->get();
        $this->assertSame(200, (int) $line->qty_m);
        $this->assertSame(
            200,
            (int) round((float) $rolls->sum(fn ($roll) => (float) $roll->actual_qty_m)),
        );
    }

    public function test_resync_updates_line_after_manual_recalc(): void
    {
        $this->seedReceivings();

        $line = ReceivingLine::query()
            ->whereHas('receiving', fn ($q) => $q->where('code', 'RC-2606-003'))
            ->firstOrFail();

        ReceivingLineTotals::sync($line->fresh());

        $this->assertSame(150, (int) $line->fresh()->qty_m);
    }
}
