<?php

namespace Tests;

use App\Models\SalesForecastLine;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DemoData::resetDatabaseUsageCacheForTesting();
        SalesForecastLine::resetDraftCacheForTesting();
    }
}
