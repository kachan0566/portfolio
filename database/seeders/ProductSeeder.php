<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::insert([
            [
                'name' => '生地A',
                'sku' => 'FAB-001',
                'price' => 1500,
                'category' => '生地',
                'unit' => 'm',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '生地B',
                'sku' => 'FAB-002',
                'price' => 2200,
                'category' => '生地',
                'unit' => 'm',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '綿糸（原糸）',
                'sku' => 'YRN-001',
                'price' => 800,
                'category' => '原材料',
                'unit' => 'kg',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
