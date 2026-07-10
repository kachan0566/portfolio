<?php

namespace Database\Seeders;

use App\Models\GreigeRoll;
use App\Models\ProductRoll;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Receiving;
use App\Models\ReceivingLine;
use App\Services\Fabric\TanRollRecorder;
use App\Support\DemoData;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;
use Illuminate\Database\Seeder;

class ReceivingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (DemoData::baseReceivingRows() as $row) {
            $po = PurchaseOrder::query()->where('code', $row['po_code'])->first();
            if ($po === null) {
                continue;
            }

            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $po->id)
                ->where('line_no', 1)
                ->first();
            if ($poLine === null) {
                continue;
            }

            Receiving::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'code' => $row['code'],
                    'received_date' => $row['date'],
                    'note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $receivingLine = ReceivingLine::query()->updateOrCreate(
                ['receiving_id' => $row['id'], 'line_no' => 1],
                [
                    'purchase_order_line_id' => $poLine->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $type = (string) $row['po_type'];

            if ($type === PurchaseOrderType::GREIGE) {
                $this->seedGreigeRolls($row, $po, $receivingLine, $now);
            } elseif ($type === PurchaseOrderType::PRODUCT) {
                $this->seedProductRolls($row, $po, $receivingLine, $now);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedGreigeRolls(array $row, PurchaseOrder $po, ReceivingLine $receivingLine, \Illuminate\Support\Carbon $now): void
    {
        $greigeSku = (string) ($row['greige_sku'] ?? '');
        $greige = DemoData::findGreige($greigeSku);
        if ($greige === null) {
            return;
        }

        $meters = (int) ($row['qty_meters'] ?? $row['qty'] ?? 0);
        if ($meters <= 0) {
            return;
        }

        $nominal = (int) ($greige->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
        $rollCount = max(1, (int) round($meters / $nominal));
        $perRoll = TanRollRecorder::distributeMeters($meters, $rollCount);

        foreach ($perRoll as $index => $actual) {
            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $code = $greigeSku.'-'.$po->code.'-'.$seq;

            GreigeRoll::query()->updateOrCreate(
                ['code' => $code],
                [
                    'greige_id' => $greige->id,
                    'purchase_order_id' => $po->id,
                    'receiving_line_id' => $receivingLine->id,
                    'tan_qty' => 1.0,
                    'actual_qty_m' => $actual,
                    'nominal_meters' => $nominal,
                    'status' => GreigeRoll::STATUS_IN_STOCK,
                    'received_date' => $row['date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function seedProductRolls(array $row, PurchaseOrder $po, ReceivingLine $receivingLine, \Illuminate\Support\Carbon $now): void
    {
        $productId = (int) ($row['product_id'] ?? 0);
        $product = DemoData::findProduct($productId);
        if ($product === null) {
            return;
        }

        $meters = (int) ($row['qty'] ?? 0);
        if ($meters <= 0) {
            return;
        }

        $qtyTan = (float) QtyHelper::roundIntegerTan(QtyHelper::tanCount($meters, $productId));
        $rollCount = max(1, (int) round($qtyTan));
        $perRoll = TanRollRecorder::distributeMeters($meters, $rollCount);
        $nominal = (int) ($product->meters_per_tan ?? DemoData::METERS_PER_TAN_PRODUCT);

        foreach ($perRoll as $index => $actual) {
            $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $code = $product->sku.'-'.$po->code.'-'.$seq;

            ProductRoll::query()->updateOrCreate(
                ['code' => $code],
                [
                    'product_id' => $productId,
                    'purchase_order_id' => $po->id,
                    'receiving_line_id' => $receivingLine->id,
                    'parent_greige_roll_id' => null,
                    'tan_qty' => 1.0,
                    'actual_qty_m' => $actual,
                    'nominal_meters' => $nominal,
                    'status' => ProductRoll::STATUS_IN_STOCK,
                    'received_date' => $row['date'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
