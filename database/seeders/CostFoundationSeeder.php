<?php

namespace Database\Seeders;

use App\Models\Greige;
use App\Models\GreigeRecipe;
use App\Models\GreigeRecipeLine;
use App\Models\MaterialPrice;
use App\Models\ProductRecipe;
use App\Models\PurchaseOrder;
use App\Models\YarnAllocation;
use App\Services\Yarn\YarnStockMovementRecorder;
use App\Support\DemoData;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use Illuminate\Database\Seeder;

class CostFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $this->seedProductRecipes($now);
        $this->seedGreigeRecipes($now);
        $this->seedMaterialPrices($now);
        $this->seedYarnAdjustments();
        $this->seedYarnAllocations();
    }

    private function seedProductRecipes(\Illuminate\Support\Carbon $now): void
    {
        foreach (DemoData::baseRecipeDataForSeed() as $productId => $recipe) {
            ProductRecipe::query()->updateOrCreate(
                ['product_id' => $productId],
                [
                    'processing_cost' => (int) $recipe['processing_cost'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    private function seedGreigeRecipes(\Illuminate\Support\Carbon $now): void
    {
        foreach (DemoData::baseGreigeRecipeDataForSeed() as $greigeSku => $recipe) {
            $greige = Greige::query()->where('sku', $greigeSku)->first();
            if ($greige === null) {
                continue;
            }

            $header = GreigeRecipe::query()->updateOrCreate(
                ['greige_id' => $greige->id],
                [
                    'loss_rate' => $recipe['loss_rate'],
                    'weaving_cost' => (int) ($recipe['weaving_cost'] ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $materialIds = [];
            foreach ($recipe['lines'] as [$materialId, $qtyPerM]) {
                $materialIds[] = (int) $materialId;
                GreigeRecipeLine::query()->updateOrCreate(
                    [
                        'greige_recipe_id' => $header->id,
                        'material_id' => (int) $materialId,
                    ],
                    [
                        'qty_per_m' => (float) $qtyPerM,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }

            GreigeRecipeLine::query()
                ->where('greige_recipe_id', $header->id)
                ->whereNotIn('material_id', $materialIds)
                ->delete();
        }
    }

    private function seedMaterialPrices(\Illuminate\Support\Carbon $now): void
    {
        foreach (DemoData::baseMaterialPriceRowsForSeed() as $row) {
            foreach ($row['prices'] as $ym => $price) {
                MaterialPrice::query()->updateOrCreate(
                    [
                        'material_id' => (int) $row['material_id'],
                        'ym' => $ym,
                    ],
                    [
                        'unit_price' => (int) $price,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }
    }

    private function seedYarnAdjustments(): void
    {
        foreach (DemoData::yarnStockBase() as $materialId => $kg) {
            YarnStockMovementRecorder::recordAdjustment(
                (int) $materialId,
                (float) $kg,
                '2026-06-01',
                '初期在庫',
            );
        }
    }

    private function seedYarnAllocations(): void
    {
        $activeStatuses = [
            PurchaseOrderStatus::DRAFT,
            PurchaseOrderStatus::ORDERED,
            PurchaseOrderStatus::PARTIAL,
        ];

        PurchaseOrder::query()
            ->where('type', PurchaseOrderType::GREIGE)
            ->whereIn('status', $activeStatuses)
            ->with('lines.greige')
            ->each(function (PurchaseOrder $po) {
                $line = $po->lines->sortBy('line_no')->first();
                if ($line === null || $line->greige === null) {
                    return;
                }

                $requirements = DemoData::greigeYarnRequirements(
                    (string) $line->greige->sku,
                    (int) ($line->qty_meters ?? 0),
                );

                if ($requirements === []) {
                    return;
                }

                $rows = [];
                foreach ($requirements as $req) {
                    $rows[] = [
                        'material_id' => (int) $req->material_id,
                        'qty_kg' => (float) $req->required_kg,
                    ];
                }

                YarnAllocation::replaceForGreigePo($po->id, $rows);
            });
    }
}
