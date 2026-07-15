<?php

namespace App\Support;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;

/**
 * 発注明細行ごとの工程ラベル（入荷有無は自動、染機投入済などは手動）。
 */
class PurchaseOrderLineDisplay
{
    public static function label(PurchaseOrder $po, PurchaseOrderLine $line): string
    {
        $status = (string) ($po->status ?? PurchaseOrderStatus::ORDERED);

        if ($status === PurchaseOrderStatus::CANCELLED) {
            return PurchaseOrderStages::LABEL_CANCELLED;
        }

        if ($status === PurchaseOrderStatus::DRAFT) {
            return PurchaseOrderStages::LABEL_DRAFT;
        }

        if ($status === PurchaseOrderStatus::RECEIVED && self::isLineFullyReceived($line)) {
            return PurchaseOrderStages::LABEL_RECEIVED;
        }

        $base = self::resolveBaseStage($po, $line);

        if (self::isLinePartial($line)) {
            return $base.PurchaseOrderStages::PARTIAL_SUFFIX;
        }

        return $base;
    }

    public static function progressPercent(PurchaseOrder $po, PurchaseOrderLine $line): int
    {
        $status = (string) ($po->status ?? '');
        if ($status === PurchaseOrderStatus::RECEIVED && self::isLineFullyReceived($line)) {
            return 100;
        }
        if ($status === PurchaseOrderStatus::CANCELLED) {
            return 0;
        }
        if ($status === PurchaseOrderStatus::DRAFT) {
            return 10;
        }

        $type = (string) ($po->type ?? PurchaseOrderType::PRODUCT);
        $base = self::resolveBaseStage($po, $line);
        if (str_ends_with($base, PurchaseOrderStages::PARTIAL_SUFFIX)) {
            $base = substr($base, 0, -strlen(PurchaseOrderStages::PARTIAL_SUFFIX));
        }

        return PurchaseOrderStages::progressPercent($type, $base);
    }

    public static function isLineReceived(PurchaseOrderLine $line): bool
    {
        return $line->receivedQty() > 0;
    }

    public static function isLinePartial(PurchaseOrderLine $line): bool
    {
        $ordered = $line->orderedQty();
        $received = $line->receivedQty();

        return $ordered > 0 && $received > 0 && $received + 0.001 < $ordered;
    }

    public static function isLineFullyReceived(PurchaseOrderLine $line): bool
    {
        $ordered = $line->orderedQty();
        $received = $line->receivedQty();

        return $ordered > 0 && $received + 0.001 >= $ordered;
    }

    public static function manualStageEditable(PurchaseOrder $po, PurchaseOrderLine $line): bool
    {
        $type = (string) ($po->type ?? '');
        if (! in_array($type, [PurchaseOrderType::GREIGE, PurchaseOrderType::PRODUCT], true)) {
            return false;
        }

        return ! self::isLineReceived($line);
    }

    public static function effectiveManualStage(PurchaseOrderLine $line): ?string
    {
        $type = (string) ($line->purchaseOrder?->type ?? PurchaseOrderType::PRODUCT);

        if ($type === PurchaseOrderType::PRODUCT) {
            return PurchaseOrderStages::normalizeProductManualStage($line->stage);
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return PurchaseOrderStages::normalizeGreigeManualStage($line->stage);
        }

        return null;
    }

    private static function resolveBaseStage(PurchaseOrder $po, PurchaseOrderLine $line): string
    {
        return match ((string) ($po->type ?? PurchaseOrderType::PRODUCT)) {
            PurchaseOrderType::YARN => self::resolveYarnStage($po, $line),
            PurchaseOrderType::GREIGE => self::resolveGreigeStage($po, $line),
            PurchaseOrderType::PRODUCT => self::resolveProductStage($line),
            default => PurchaseOrderStages::LABEL_DRAFT,
        };
    }

    private static function resolveYarnStage(PurchaseOrder $po, PurchaseOrderLine $line): string
    {
        if (self::isLineReceived($line)) {
            return PurchaseOrderStages::YARN_RECEIVED_AT_WEAVING;
        }

        return PurchaseOrderStages::YARN_ORDERED;
    }

    private static function resolveGreigeStage(PurchaseOrder $po, PurchaseOrderLine $line): string
    {
        if (self::isLineReceived($line)) {
            return PurchaseOrderStages::GREIGE_SHIPPED;
        }

        $manual = self::effectiveManualStage($line);
        if ($manual === PurchaseOrderStages::GREIGE_WEAVING) {
            return PurchaseOrderStages::GREIGE_WEAVING;
        }

        if (self::lineYarnReady($line)) {
            return PurchaseOrderStages::GREIGE_YARN_READY;
        }

        return PurchaseOrderStages::GREIGE_ORDERED;
    }

    private static function resolveProductStage(PurchaseOrderLine $line): string
    {
        if (self::isLineReceived($line)) {
            return PurchaseOrderStages::PRODUCT_IN_STOCK;
        }

        return self::effectiveManualStage($line) ?? PurchaseOrderStages::PRODUCT_DYEING;
    }

    private static function lineYarnReady(PurchaseOrderLine $line): bool
    {
        $sku = (string) ($line->greige?->sku ?? '');
        $meters = (int) ($line->qty_meters ?? 0);
        if ($sku === '' || $meters <= 0) {
            return false;
        }

        return GreigeYarnReadiness::allRequiredYarnReceived((object) [
            'yarn_requirements' => DemoData::greigeYarnRequirements($sku, $meters),
            'greige_sku' => $sku,
            'qty_meters' => $meters,
        ]);
    }
}
