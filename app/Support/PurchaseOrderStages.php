<?php

namespace App\Support;

/**
 * 発注種別ごとの工程定義（旧 PO_STAGES 8段階の代替）。
 */
class PurchaseOrderStages
{
    public const LABEL_DRAFT = '下書き';

    public const LABEL_CANCELLED = 'キャンセル';

    public const LABEL_RECEIVED = '入荷完了';

    public const PARTIAL_SUFFIX = '（一部入荷）';

    // 糸発注
    public const YARN_ORDERED = '糸発注済';

    public const YARN_SHIPPED = '糸出荷済';

    public const YARN_RECEIVED_AT_WEAVING = '織工場への糸入荷済';

    // 生機発注
    public const GREIGE_ORDERED = '発注済';

    public const GREIGE_YARN_READY = '糸入荷済';

    public const GREIGE_WEAVING = '織編機投入済';

    public const GREIGE_SHIPPED = '生機出荷済';

    // 製品発注
    public const PRODUCT_DYEING = '染機投入済';

    public const PRODUCT_IN_STOCK = '製品在庫中';

    public const PRODUCT_SHIPPED = '製品出荷済';

    /** @return list<string> */
    public static function labelsFor(string $type): array
    {
        return match ($type) {
            PurchaseOrderType::YARN => [
                self::YARN_ORDERED,
                self::YARN_SHIPPED,
                self::YARN_RECEIVED_AT_WEAVING,
            ],
            PurchaseOrderType::GREIGE => [
                self::GREIGE_ORDERED,
                self::GREIGE_YARN_READY,
                self::GREIGE_WEAVING,
                self::GREIGE_SHIPPED,
            ],
            PurchaseOrderType::PRODUCT => [
                self::PRODUCT_DYEING,
                self::PRODUCT_IN_STOCK,
                self::PRODUCT_SHIPPED,
            ],
            default => [],
        };
    }

    /** @return list<string> */
    public static function manualOptionsFor(string $type): array
    {
        return match ($type) {
            PurchaseOrderType::GREIGE => [self::GREIGE_WEAVING],
            PurchaseOrderType::PRODUCT => [self::PRODUCT_DYEING],
            default => [],
        };
    }

    public static function sortOrder(string $type, string $label): int
    {
        $labels = self::labelsFor($type);
        $idx = array_search($label, $labels, true);

        return $idx === false ? 99 : (int) $idx;
    }

    public static function progressPercent(string $type, string $baseLabel): int
    {
        $labels = self::labelsFor($type);
        if ($labels === []) {
            return 0;
        }

        $idx = array_search($baseLabel, $labels, true);
        if ($idx === false) {
            return 0;
        }

        return (int) round(($idx + 1) / count($labels) * 100);
    }

    /** 製品発注の旧8段階 stage を手動工程に正規化 */
    public static function normalizeProductManualStage(?string $stage): string
    {
        $legacy = [
            '原材料未発注',
            '原材料発注済',
            '原材料出荷済',
            '織編機投入済',
            '生機出荷済',
        ];

        if ($stage === null || $stage === '' || in_array($stage, $legacy, true)) {
            return self::PRODUCT_DYEING;
        }

        if (in_array($stage, [self::PRODUCT_DYEING, self::PRODUCT_IN_STOCK, self::PRODUCT_SHIPPED], true)) {
            return $stage;
        }

        return self::PRODUCT_DYEING;
    }

    public static function normalizeGreigeManualStage(?string $stage): ?string
    {
        if ($stage === self::GREIGE_WEAVING) {
            return self::GREIGE_WEAVING;
        }

        return null;
    }
}
