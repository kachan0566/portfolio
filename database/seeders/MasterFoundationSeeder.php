<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Greige;
use App\Models\ShipTo;
use App\Models\Supplier;
use App\Support\DemoData;
use Illuminate\Database\Seeder;

class MasterFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (DemoData::customers() as $row) {
            Customer::query()->updateOrCreate(
                ['id' => $row->id],
                [
                    'name' => $row->name,
                    'contact' => $row->contact ?? null,
                    'tel' => $row->tel ?? null,
                    'note' => $row->note ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach (DemoData::suppliers() as $row) {
            Supplier::query()->updateOrCreate(
                ['id' => $row->id],
                [
                    'name' => $row->name,
                    'contact' => $row->contact ?? null,
                    'tel' => $row->tel ?? null,
                    'type' => $row->type,
                    'note' => $row->note ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach (DemoData::shipTos() as $row) {
            ShipTo::query()->updateOrCreate(
                ['id' => $row->id],
                [
                    'name' => $row->name,
                    'type' => $row->type,
                    'note' => $row->note ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach (DemoData::greiges() as $row) {
            Greige::query()->updateOrCreate(
                ['id' => $row->id],
                [
                    'sku' => $row->sku,
                    'name' => $row->name,
                    'category' => $row->category,
                    'unit' => $row->unit,
                    'meters_per_tan' => $row->meters_per_tan,
                    'note' => $row->note ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
