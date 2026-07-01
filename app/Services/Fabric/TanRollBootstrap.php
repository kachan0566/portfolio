<?php

namespace App\Services\Fabric;

use App\Support\DemoData;
use App\Support\FabricTanRoll;
use App\Support\QtyHelper;
use App\Support\PurchaseOrderType;

/**
 * デモ用：既存入荷実績から反明細の初期データを生成する。
 */
class TanRollBootstrap
{
    public static function run(): void
    {
        $rolls = [];

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
                $rolls[] = [
                    'id' => count($rolls) + 1,
                    'code' => $greigeSku.'-'.$po->code.'-'.$seq,
                    'po_id' => $poId,
                    'greige_sku' => $greigeSku,
                    'product_id' => null,
                    'parent_roll_id' => null,
                    'stage' => FabricTanRoll::STAGE_GREIGE_WIP,
                    'nominal_meters' => $nominal,
                    'weaving_meters' => $actual,
                    'dyeing_meters' => null,
                    'tan_qty' => 1.0,
                    'measured_at' => (string) ($receiving->date ?? date('Y-m-d')),
                    'weaving_measured_at' => (string) ($receiving->date ?? date('Y-m-d')),
                    'dyeing_measured_at' => null,
                ];
            }
        }

        FabricTanRoll::replaceAll($rolls);

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
            $qtyTan = QtyHelper::tanCount($meters, $productId);
            TanRollRecorder::recordProductReceiving((int) $po->id, $productId, $qtyTan, $meters, (string) ($receiving->date ?? date('Y-m-d')));
        }
    }
}
