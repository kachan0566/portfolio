<?php

namespace App\Support;

/**
 * 発注の画面表示用ラベル（status と工程を1つにまとめる）。
 */
class PurchaseOrderDisplay
{
    public static function label(object $po): string
    {
        $status = (string) ($po->status ?? PurchaseOrderStatus::ORDERED);

        if ($status === PurchaseOrderStatus::CANCELLED) {
            return PurchaseOrderStages::LABEL_CANCELLED;
        }

        if ($status === PurchaseOrderStatus::DRAFT) {
            return PurchaseOrderStages::LABEL_DRAFT;
        }

        if ($status === PurchaseOrderStatus::RECEIVED) {
            return PurchaseOrderStages::LABEL_RECEIVED;
        }

        $base = self::resolveBaseStage($po);

        if (self::isPartial($po)) {
            return $base.PurchaseOrderStages::PARTIAL_SUFFIX;
        }

        return $base;
    }

    public static function baseStage(object $po): string
    {
        $status = (string) ($po->status ?? PurchaseOrderStatus::ORDERED);
        if (in_array($status, [PurchaseOrderStatus::CANCELLED, PurchaseOrderStatus::DRAFT, PurchaseOrderStatus::RECEIVED], true)) {
            return self::label($po);
        }

        return self::resolveBaseStage($po);
    }

    public static function isPartial(object $po): bool
    {
        $status = (string) ($po->status ?? '');
        if ($status === PurchaseOrderStatus::PARTIAL) {
            return true;
        }

        $poId = (int) ($po->id ?? 0);
        $ordered = DemoData::purchaseOrderOrderedQty($po);
        $received = DemoState::effectiveReceivedQty($poId, $po);

        return $ordered > 0 && $received > 0 && $received + 0.001 < $ordered;
    }

    public static function progressPercent(object $po): int
    {
        $status = (string) ($po->status ?? '');
        if ($status === PurchaseOrderStatus::RECEIVED) {
            return 100;
        }
        if ($status === PurchaseOrderStatus::CANCELLED) {
            return 0;
        }
        if ($status === PurchaseOrderStatus::DRAFT) {
            return 10;
        }

        $type = (string) ($po->type ?? PurchaseOrderType::PRODUCT);

        return PurchaseOrderStages::progressPercent($type, self::resolveBaseStage($po));
    }

    /** @return array<string, string> */
    public static function filterOptions(): array
    {
        $options = [
            PurchaseOrderStages::LABEL_DRAFT => PurchaseOrderStages::LABEL_DRAFT,
            PurchaseOrderStages::LABEL_CANCELLED => PurchaseOrderStages::LABEL_CANCELLED,
            PurchaseOrderStages::LABEL_RECEIVED => PurchaseOrderStages::LABEL_RECEIVED,
        ];

        foreach (PurchaseOrderType::all() as $type) {
            foreach (PurchaseOrderStages::labelsFor($type) as $label) {
                $options[$label] = $label;
                $partial = $label.PurchaseOrderStages::PARTIAL_SUFFIX;
                $options[$partial] = $partial;
            }
        }

        return $options;
    }

    public static function manualStageEditable(object $po): bool
    {
        $type = (string) ($po->type ?? '');
        if (! in_array($type, [PurchaseOrderType::GREIGE, PurchaseOrderType::PRODUCT], true)) {
            return false;
        }

        if (self::isPartial($po) || (string) ($po->status ?? '') === PurchaseOrderStatus::RECEIVED) {
            return $type === PurchaseOrderType::PRODUCT
                ? DemoState::effectiveReceivedQty((int) $po->id, $po) <= 0
                : false;
        }

        if ($type === PurchaseOrderType::GREIGE) {
            return DemoState::effectiveReceivedQty((int) $po->id, $po) <= 0;
        }

        return DemoState::effectiveReceivedQty((int) $po->id, $po) <= 0;
    }

    public static function effectiveManualStage(object $po): ?string
    {
        $poId = (int) ($po->id ?? 0);
        $overlay = DemoState::effectivePoStage($poId);
        $type = (string) ($po->type ?? '');

        if ($type === PurchaseOrderType::PRODUCT) {
            $raw = $overlay !== '' ? $overlay : (string) ($po->manual_stage ?? $po->stage ?? '');

            return PurchaseOrderStages::normalizeProductManualStage($raw !== '' ? $raw : null);
        }

        if ($type === PurchaseOrderType::GREIGE) {
            $raw = $overlay !== '' ? $overlay : (string) ($po->manual_stage ?? '');

            return PurchaseOrderStages::normalizeGreigeManualStage($raw !== '' ? $raw : null);
        }

        return null;
    }

    private static function resolveBaseStage(object $po): string
    {
        return match ((string) ($po->type ?? PurchaseOrderType::PRODUCT)) {
            PurchaseOrderType::YARN => self::resolveYarnStage($po),
            PurchaseOrderType::GREIGE => self::resolveGreigeStage($po),
            PurchaseOrderType::PRODUCT => self::resolveProductStage($po),
            default => PurchaseOrderStages::LABEL_DRAFT,
        };
    }

    private static function resolveYarnStage(object $po): string
    {
        $poId = (int) ($po->id ?? 0);
        $received = DemoState::effectiveReceivedQty($poId, $po);

        if ($received > 0) {
            return PurchaseOrderStages::YARN_RECEIVED_AT_WEAVING;
        }

        if (! empty($po->shipped_at)) {
            return PurchaseOrderStages::YARN_SHIPPED;
        }

        return PurchaseOrderStages::YARN_ORDERED;
    }

    private static function resolveGreigeStage(object $po): string
    {
        $poId = (int) ($po->id ?? 0);
        $received = DemoState::effectiveReceivedQty($poId, $po);

        if ($received > 0) {
            return PurchaseOrderStages::GREIGE_SHIPPED;
        }

        $manual = self::effectiveManualStage($po);
        if ($manual === PurchaseOrderStages::GREIGE_WEAVING) {
            return PurchaseOrderStages::GREIGE_WEAVING;
        }

        if (GreigeYarnReadiness::allRequiredYarnReceived($po)) {
            return PurchaseOrderStages::GREIGE_YARN_READY;
        }

        return PurchaseOrderStages::GREIGE_ORDERED;
    }

    private static function resolveProductStage(object $po): string
    {
        $poId = (int) ($po->id ?? 0);
        $received = DemoState::effectiveReceivedQty($poId, $po);

        if ($received > 0) {
            return PurchaseOrderStages::PRODUCT_IN_STOCK;
        }

        return self::effectiveManualStage($po) ?? PurchaseOrderStages::PRODUCT_DYEING;
    }
}
