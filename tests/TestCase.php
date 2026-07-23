<?php

namespace Tests;

use App\Models\SalesForecastLine;
use App\Services\Inventory\GreigeDyeInput;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        GreigeDyeInput::resetBootstrapForTesting();
        SalesForecastLine::resetDraftCacheForTesting();
    }
}
