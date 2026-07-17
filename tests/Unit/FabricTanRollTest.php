<?php

namespace Tests\Unit;

use App\Services\Fabric\TanRollRecorder;
use App\Support\DemoData;
use App\Support\FabricTanRoll;
use App\Support\GreigeInventory;
use App\Support\GreigeRoll;
use App\Support\QtyHelper;
use Tests\TestCase;

class FabricTanRollTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FabricTanRoll::resetBootstrap();
    }

    public function test_bootstrap_creates_weaving_rolls_from_greige_receiving(): void
    {
        FabricTanRoll::ensureBootstrapped();

        $rolls = FabricTanRoll::forPo(5);
        $this->assertGreaterThanOrEqual(2, $rolls->count());
        $this->assertSame(200.0, $rolls->sum(fn ($roll) => FabricTanRoll::actualMeters($roll)));
    }

    public function test_record_weaving_completion_splits_actual_meters(): void
    {
        FabricTanRoll::replaceAll([]);

        $created = TanRollRecorder::recordWeavingCompletion(99, 'KB-A', 2, 198, '2026-06-20');
        $this->assertCount(2, $created);
        $this->assertSame(198.0, collect($created)->sum(fn ($roll) => FabricTanRoll::actualMeters($roll)));
    }

    public function test_record_dyeing_completion_creates_product_rolls(): void
    {
        FabricTanRoll::replaceAll([]);
        TanRollRecorder::recordWeavingCompletion(5, 'KB-T', 2, 200, '2026-06-18');

        foreach (GreigeRoll::inStockForSku('KB-T') as $roll) {
            GreigeRoll::update((int) $roll->id, [
                'status' => GreigeRoll::STATUS_IN_DYEING,
            ]);
        }

        $result = TanRollRecorder::recordDyeingCompletion(2, 3, '2026-06-16');
        $this->assertGreaterThan(0, $result['total_meters']);
        $this->assertNotEmpty($result['product_rolls']);

        $productRolls = FabricTanRoll::forPo(2)->filter(fn ($r) => $r->stage === FabricTanRoll::STAGE_PRODUCT);
        $this->assertNotEmpty($productRolls);
    }

    public function test_greige_inventory_uses_roll_actual_meters(): void
    {
        FabricTanRoll::ensureBootstrapped();

        $entry = GreigeInventory::entries()->firstWhere('po_id', 5);
        $this->assertNotNull($entry);
        $this->assertSame(200, $entry->qty_meters);
        $this->assertGreaterThanOrEqual(2, $entry->roll_count);
    }

    public function test_format_roll_shows_variance(): void
    {
        $roll = (object) [
            'nominal_meters' => 100,
            'weaving_meters' => 102.0,
            'dyeing_meters' => null,
        ];

        $this->assertStringContainsString('+2', QtyHelper::formatRoll($roll, true, null, 'KB-A'));
    }
}
