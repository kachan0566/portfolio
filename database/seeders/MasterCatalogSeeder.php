<?php

namespace Database\Seeders;

use App\Models\Greige;
use App\Models\Material;
use App\Models\Product;
use App\Support\DemoData;
use Illuminate\Database\Seeder;

class MasterCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (DemoData::materials() as $row) {
            Material::query()->updateOrCreate(
                ['id' => $row->id],
                [
                    'sku' => $row->sku,
                    'type' => $row->type,
                    'name' => $row->name,
                    'unit' => $row->unit,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $greigeIdsBySku = Greige::query()->pluck('id', 'sku');

        foreach (DemoData::products() as $row) {
            $greigeId = $greigeIdsBySku->get($row->greige_sku);
            if ($greigeId === null) {
                continue;
            }

            Product::query()->updateOrCreate(
                ['id' => $row->id],
                [
                    'greige_id' => $greigeId,
                    'name' => $row->sku,
                    'sku' => $row->sku,
                    'color' => $row->color,
                    'price' => $row->price,
                    'category' => $row->category,
                    'unit' => $row->unit,
                    'meters_per_tan' => $row->meters_per_tan,
                    'stock_min_m' => $row->stock_min,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
