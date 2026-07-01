<?php

namespace Tests\Unit;

use App\Support\QtyHelper;
use Tests\TestCase;

class QtyHelperTest extends TestCase
{
    public function test_format_displays_tan_and_meters_equally(): void
    {
        $this->assertSame('2.4反 / 120m', QtyHelper::format(120, 1));
    }

    public function test_greige_tan_uses_greige_meters_per_tan(): void
    {
        $this->assertSame('1.2反 / 120m', QtyHelper::format(120, null, true, 'KB-A'));
    }

    public function test_product_tan_from_greige_meters(): void
    {
        $this->assertSame(2.0, QtyHelper::productTanFromGreigeMeters(100, 1));
    }

    public function test_round_tan_snaps_to_five_hundredths(): void
    {
        $this->assertSame(2.45, QtyHelper::roundTan(2.47));
        $this->assertSame(2.5, QtyHelper::roundTan(2.48));
        $this->assertSame(0.05, QtyHelper::roundTan(0.04));
    }

    public function test_is_valid_tan_step(): void
    {
        $this->assertTrue(QtyHelper::isValidTanStep(2.5));
        $this->assertTrue(QtyHelper::isValidTanStep(0.05));
        $this->assertFalse(QtyHelper::isValidTanStep(2.03));
        $this->assertFalse(QtyHelper::isValidTanStep(0));
    }

    public function test_format_from_tan(): void
    {
        $this->assertSame('2.5反 / 125m', QtyHelper::formatFromTan(2.5, 1));
    }

    public function test_sum_tan_from_lines_aggregates_per_product(): void
    {
        $lines = [
            (object) ['product_id' => 1, 'qty' => 100],
            (object) ['product_id' => 3, 'qty' => 70],
        ];

        $this->assertSame(3.4, QtyHelper::sumTanFromLines($lines, 'qty'));
    }

    public function test_format_aggregate_from_lines(): void
    {
        $lines = [
            (object) ['product_id' => 1, 'qty' => 120],
        ];

        $this->assertSame('2.4反 / 120m', QtyHelper::formatAggregateFromLines($lines, 'qty', 1));
    }
}
