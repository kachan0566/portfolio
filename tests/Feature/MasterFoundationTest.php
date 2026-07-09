<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Greige;
use App\Models\ShipTo;
use App\Models\Supplier;
use App\Support\DemoData;
use App\Support\PurchaseOrderType;
use Database\Seeders\MasterFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_foundation_seeder_matches_demo_data(): void
    {
        $this->seed(MasterFoundationSeeder::class);

        $this->assertSame(DemoData::customers()->count(), Customer::query()->count());
        $this->assertSame(DemoData::suppliers()->count(), Supplier::query()->count());
        $this->assertSame(DemoData::shipTos()->count(), ShipTo::query()->count());
        $this->assertSame(DemoData::greiges()->count(), Greige::query()->count());

        $customer = Customer::query()->find(1);
        $this->assertNotNull($customer);
        $this->assertSame('東レ商事', $customer->name);
        $this->assertSame('田中 一郎', $customer->contact);

        $greige = Greige::query()->where('sku', 'KB-A')->first();
        $this->assertNotNull($greige);
        $this->assertSame('生機A', $greige->name);
        $this->assertSame(100, $greige->meters_per_tan);
    }

    public function test_supplier_for_purchase_type_matches_demo_data(): void
    {
        $this->seed(MasterFoundationSeeder::class);

        $dbIds = Supplier::forPurchaseType(PurchaseOrderType::YARN)->pluck('id')->all();
        $demoIds = DemoData::suppliersForPurchaseType(PurchaseOrderType::YARN)->pluck('id')->all();

        $this->assertSame($demoIds, $dbIds);
    }

    public function test_customer_index_page_loads_from_database(): void
    {
        $this->seed(MasterFoundationSeeder::class);

        $response = $this->get(route('customers.index'));

        $response->assertOk();
        $response->assertSee('東レ商事');
    }

    public function test_supplier_index_page_loads_from_database(): void
    {
        $this->seed(MasterFoundationSeeder::class);

        $response = $this->get(route('suppliers.index'));

        $response->assertOk();
        $response->assertSee('紡績ワークス');
    }
}
