<?php

namespace App\Services\Inventory;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Support\DemoData;
use App\Support\GreigeRoll;
use App\Support\GreigeSupply;
use App\Support\PurchaseOrderLineDisplay;
use App\Support\PurchaseOrderStages;
use App\Support\PurchaseOrderStatus;
use App\Support\PurchaseOrderType;
use App\Support\QtyHelper;

/**
 * 製品発注明細の「染機投入済」に連動して、生機反を在庫から染色仕掛（in_dyeing）へ移す。
 */
class GreigeDyeInput
{
    private const BOOTSTRAP_FLAG = 'greige_dye_input_bootstrapped.flag';

    private static bool $bootstrapping = false;

    public static function applyLineStageChange(PurchaseOrderLine $line, ?string $stageValue): ?string
    {
        $line->loadMissing(['product.greige', 'purchaseOrder']);

        if ((string) ($line->purchaseOrder?->type ?? '') !== PurchaseOrderType::PRODUCT) {
            return null;
        }

        if (PurchaseOrderLineDisplay::isLineReceived($line)) {
            return null;
        }

        $wantsDyeing = $stageValue === PurchaseOrderStages::PRODUCT_DYEING;

        if ($wantsDyeing) {
            $error = self::commitLine($line);
            if ($error !== null) {
                return $error;
            }

            $line->update(['stage' => PurchaseOrderStages::PRODUCT_DYEING]);

            return null;
        }

        self::revertLine($line);
        $line->update(['stage' => null]);

        return null;
    }

    public static function commitLine(PurchaseOrderLine $line): ?string
    {
        $line->loadMissing(['product.greige', 'purchaseOrder']);

        $requiredMeters = (int) round($line->remainingQty());
        if ($requiredMeters <= 0) {
            self::revertLine($line);

            return null;
        }

        $productId = (int) ($line->product_id ?? 0);
        $greigeSku = GreigeSupply::greigeSkuForProduct($productId);
        if ($greigeSku === null) {
            return '製品に紐づく生機品番が見つかりません。';
        }

        $alreadyAllocated = (int) round(self::allocatedMetersForLine((int) $line->id));
        if ($alreadyAllocated >= $requiredMeters) {
            return null;
        }

        self::revertLine($line);

        if (! GreigeSupply::canFulfillProductMeters($productId, $requiredMeters)) {
            return GreigeSupply::shortageMessage($productId, $requiredMeters)
                ?? '生機在庫が不足しています。';
        }

        self::allocateMetersFifo($greigeSku, (float) $requiredMeters, (int) $line->id);

        return null;
    }

    public static function revertLine(PurchaseOrderLine $line): void
    {
        foreach (GreigeRoll::forDyeingLine((int) $line->id) as $roll) {
            GreigeRoll::update((int) $roll->id, [
                'status' => GreigeRoll::STATUS_IN_STOCK,
                'dyeing_purchase_order_line_id' => null,
            ]);
        }
    }

    public static function revertPurchaseOrder(int $purchaseOrderId): void
    {
        $po = PurchaseOrder::query()->with('lines')->find($purchaseOrderId);
        if ($po === null || $po->type !== PurchaseOrderType::PRODUCT) {
            return;
        }

        foreach ($po->lines as $line) {
            self::revertLine($line);
            if ((string) ($line->stage ?? '') === PurchaseOrderStages::PRODUCT_DYEING) {
                $line->update(['stage' => null]);
            }
        }
    }

    public static function bootstrapIfNeeded(): void
    {
        if (self::$bootstrapping || ! DemoData::usesGreigeRollDatabase()) {
            return;
        }

        $flag = storage_path('app/'.self::BOOTSTRAP_FLAG);
        if (is_file($flag)) {
            return;
        }

        self::bootstrap();

        $dir = dirname($flag);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($flag, date('c'));
    }

    public static function resetBootstrapForTesting(): void
    {
        $flag = storage_path('app/'.self::BOOTSTRAP_FLAG);
        if (is_file($flag)) {
            unlink($flag);
        }
    }

    public static function bootstrap(): void
    {
        if (self::$bootstrapping) {
            return;
        }

        self::$bootstrapping = true;

        try {
            PurchaseOrderLine::query()
                ->where('stage', PurchaseOrderStages::PRODUCT_DYEING)
                ->whereHas('purchaseOrder', function ($query) {
                    $query->where('type', PurchaseOrderType::PRODUCT)
                        ->whereNot('status', PurchaseOrderStatus::CANCELLED);
                })
                ->with(['product.greige', 'purchaseOrder'])
                ->orderBy('id')
                ->each(function (PurchaseOrderLine $line) {
                    if ($line->remainingQty() <= 0) {
                        return;
                    }

                    self::commitLine($line);
                });
        } finally {
            self::$bootstrapping = false;
        }
    }

    public static function allocatedMetersForLine(int $lineId): float
    {
        return (float) GreigeRoll::forDyeingLine($lineId)
            ->sum(fn ($roll) => (float) $roll->actual_qty_m);
    }

    private static function allocateMetersFifo(string $greigeSku, float $requiredMeters, int $lineId): void
    {
        $remaining = round($requiredMeters, 2);

        foreach (GreigeRoll::inStockForSku($greigeSku) as $roll) {
            if ($remaining <= 0.0001) {
                break;
            }

            $rollMeters = (float) $roll->actual_qty_m;
            if ($rollMeters <= 0.0001) {
                continue;
            }

            if ($rollMeters <= $remaining + 0.0001) {
                GreigeRoll::update((int) $roll->id, [
                    'status' => GreigeRoll::STATUS_IN_DYEING,
                    'dyeing_purchase_order_line_id' => $lineId,
                ]);
                $remaining = round($remaining - $rollMeters, 2);

                continue;
            }

            self::splitRollForDyeing((int) $roll->id, $remaining, $lineId);
            $remaining = 0.0;
        }
    }

    private static function splitRollForDyeing(int $rollId, float $takeMeters, int $lineId): void
    {
        $roll = GreigeRoll::find($rollId);
        if ($roll === null) {
            return;
        }

        $rollMeters = (float) $roll->actual_qty_m;
        $rollTan = (float) $roll->tan_qty;
        if ($rollMeters <= 0 || $takeMeters <= 0) {
            return;
        }

        $takeMeters = round(min($takeMeters, $rollMeters), 2);
        $takeTan = QtyHelper::roundReceivingTan($rollTan * ($takeMeters / $rollMeters));
        $remainMeters = round($rollMeters - $takeMeters, 2);
        $remainTan = QtyHelper::roundReceivingTan(max(0.0, $rollTan - $takeTan));

        GreigeRoll::update($rollId, [
            'tan_qty' => $remainTan,
            'actual_qty_m' => $remainMeters,
            'status' => GreigeRoll::STATUS_IN_STOCK,
            'dyeing_purchase_order_line_id' => null,
        ]);

        GreigeRoll::create([
            'code' => $roll->code.'-D',
            'greige_sku' => $roll->greige_sku,
            'purchase_order_id' => $roll->purchase_order_id,
            'receiving_id' => $roll->receiving_id ?? null,
            'tan_qty' => $takeTan,
            'actual_qty_m' => $takeMeters,
            'nominal_meters' => (int) $roll->nominal_meters,
            'status' => GreigeRoll::STATUS_IN_DYEING,
            'dyeing_purchase_order_line_id' => $lineId,
            'received_date' => $roll->received_date,
        ]);
    }
}
