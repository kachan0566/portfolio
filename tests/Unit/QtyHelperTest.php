<?php

namespace Tests\Unit;

use App\Support\QtyHelper;
use PHPUnit\Framework\TestCase;

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
}
