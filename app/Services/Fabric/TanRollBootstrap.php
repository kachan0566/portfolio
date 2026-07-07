<?php

namespace App\Services\Fabric;

use App\Support\DemoData;
use App\Support\GreigeRoll;
use App\Support\ProductRoll;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;

/**
 * デモ用：既存入荷実績から反明細の初期データを生成する。
 */
class TanRollBootstrap
{
    public static function run(): void
    {
        $greigeRolls = [];

        foreach (DemoData::receivings() as $receiving) {
            if (($receiving->po_type ?? '') !== PurchaseOrderType::GREIGE) {
                continue;
            }

            $po = DemoData::purchaseOrders()->firstWhere('code', $receiving->po_code);
            if ($po === null) {
                continue;
            }

            $poId = (int) $po->id;
            $meters = (int) ($receiving->qty_meters ?? $receiving->qty ?? 0);
            if ($meters <= 0) {
                continue;
            }

            $greigeSku = (string) ($receiving->greige_sku ?? $receiving->sku ?? '');
            $greige = DemoData::findGreige($greigeSku);
            if ($greige === null) {
                continue;
            }

            $nominal = (int) ($greige->meters_per_tan ?? DemoData::METERS_PER_TAN_GREIGE);
            $rollCount = max(1, (int) round($meters / $nominal));
            $perRoll = TanRollRecorder::distributeMeters($meters, $rollCount);

            foreach ($perRoll as $index => $actual) {
                $seq = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $greigeRolls[] = [
                    'id' => count($greigeRolls) + 1,
                    'code' => $greigeSku.'-'.$po->code.'-'.$seq,
                    'purchase_order_id' => $poId,
                    'greige_sku' => $greigeSku,
                    'tan_qty' => 1.0,
                    'actual_qty_m' => $actual,
                    'nominal_meters' => $nominal,
                    'status' => GreigeRoll::STATUS_IN_STOCK,
                    'received_date' => (string) ($receiving->date ?? date('Y-m-d')),
                ];
            }
        }

        GreigeRoll::replaceAll($greigeRolls);

        foreach (DemoData::receivings() as $receiving) {
            if (($receiving->po_type ?? '') !== PurchaseOrderType::PRODUCT) {
                continue;
            }

            $po = DemoData::purchaseOrders()->firstWhere('code', $receiving->po_code);
            if ($po === null) {
                continue;
            }

            $meters = (int) ($receiving->qty ?? 0);
            if ($meters <= 0) {
                continue;
            }

            $productId = (int) ($receiving->product_id ?? $po->product_id ?? 0);
            $qtyTan = (float) QtyHelper::roundIntegerTan(QtyHelper::tanCount($meters, $productId));
            TanRollRecorder::recordProductReceiving(
                (int) $po->id,
                $productId,
                $qtyTan,
                $meters,
                (string) ($receiving->date ?? date('Y-m-d')),
            );
        }

        $flag = storage_path('app/'.GreigeRoll::BOOTSTRAP_FLAG);
        $dir = dirname($flag);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($flag, date('c'));

        $productFlag = storage_path('app/'.ProductRoll::BOOTSTRAP_FLAG);
        file_put_contents($productFlag, date('c'));
    }
}
