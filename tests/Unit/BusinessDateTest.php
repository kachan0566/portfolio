<?php

namespace Tests\Unit;

use App\Support\BusinessDate;
use Tests\TestCase;

class BusinessDateTest extends TestCase
{
    public function test_fixed_business_date_controls_today_and_current_month(): void
    {
        config()->set('business.fixed_date', '2026-08-19');

        $this->assertSame('2026-08-19', BusinessDate::today());
        $this->assertSame('2026-08', BusinessDate::currentYm());
        $this->assertSame('Asia/Tokyo', BusinessDate::now()->timezoneName);
    }
}
