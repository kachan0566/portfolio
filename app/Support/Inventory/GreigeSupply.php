<?php

namespace App\Support\Inventory;

use App\Models\Purchase\PurchaseOrder;
use App\Support\Qty\QtyHelper;

/**
 * 製品発注向け：生機の供給量（染工場仕掛 + 生機発注残）の判定。
 */
class GreigeSupply
{
    public static function greigeSkuForProduct(int $productId): ?string
    {
        return \App\Support\Master\MasterCatalog::findProduct($productId)?->greige_sku;
    }

    /** 染工場仕掛の生機（m）— 生機発注入荷実績 */
    public static function dyeFactoryMeters(string $greigeSku): float
    {
        return GreigeInventory::totalMetersForSku($greigeSku);
    }

    /** 生機発注の未入荷残（m） */
    public static function greigePoRemainingMeters(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        $total = 0.0;
        PurchaseOrder::query()
            ->where('type', \App\Support\Purchase\PurchaseOrderType::GREIGE)
            ->whereIn('status', [
                \App\Support\Purchase\PurchaseOrderStatus::ORDERED,
                \App\Support\Purchase\PurchaseOrderStatus::PARTIAL,
            ])
            ->with(['lines.greige'])
            ->each(function (PurchaseOrder $po) use ($greigeSku, &$total) {
                foreach ($po->lines as $line) {
                    if (($line->greige?->sku ?? '') !== $greigeSku) {
                        continue;
                    }
                    $ordered = (float) ($line->qty_meters ?? 0);
                    $received = (float) ($line->received_qty_m ?? 0);
                    $total += max(0, $ordered - $received);
                }
            });

        return $total;
    }

    public static function availableMeters(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        return self::dyeFactoryMeters($greigeSku) + self::greigePoRemainingMeters($greigeSku, $excludeProductPoId);
    }

    public static function canFulfillProductMeters(int $productId, float $requiredMeters, ?int $excludeProductPoId = null): bool
    {
        $sku = self::greigeSkuForProduct($productId);
        if ($sku === null || $requiredMeters <= 0) {
            return false;
        }

        return self::availableMeters($sku, $excludeProductPoId) >= $requiredMeters;
    }

    public static function shortageMessage(int $productId, float $requiredMeters, ?int $excludeProductPoId = null): ?string
    {
        $sku = self::greigeSkuForProduct($productId);
        if ($sku === null) {
            return '製品に紐づく生機品番が見つかりません。';
        }

        $available = self::availableMeters($sku, $excludeProductPoId);
        if ($available >= $requiredMeters) {
            return null;
        }

        $short = $requiredMeters - $available;
        $greige = \App\Support\Master\MasterCatalog::findGreige($sku);

        return ($greige?->name ?? $sku).'（'.$sku.'）が '.\App\Support\Qty\QtyHelper::formatMeters($short).'m 不足しています（必要 '.\App\Support\Qty\QtyHelper::formatMeters($requiredMeters).'m / 利用可能 '.\App\Support\Qty\QtyHelper::formatMeters($available).'m）。';
    }

    /**
     * 染工場仕掛の生機在庫反数を返す。
     *
     * 染色待ちの物理反（在庫中・一部消費）の tan_qty 合計。反明細が無い旧データは入荷mからの換算反になる。
     *
     * @param  string  $greigeSku  生機 SKU。在庫行の品番絞り込みに使う
     * @return float  在庫反数。該当なしのときは 0.0
     *
     * @see GreigeInventory::totalTanForSku()
     */
    public static function dyeFactoryTan(string $greigeSku): float
    {
        return GreigeInventory::totalTanForSku($greigeSku);
    }

    /**
     * 生機発注の未入荷残反数を品番ごとに合計して返す。
     *
     * 処理の流れ:
     * 1. 状態が発注済・一部入荷の生機発注を読む
     * 2. 明細の生機 SKU が一致する行だけ残す
     * 3. 各行の remainingTan()（発注反 − 入荷済反）を足す
     *
     * @param  string  $greigeSku  生機 SKU。明細の greige.sku と一致比較する
     * @param  int|null  $excludeProductPoId  製品発注ID。生機発注の残は対象外のため未使用（m版と同じ引数）
     * @return float  未入荷残反数の合計。該当なしのときは 0.0
     */
    public static function greigePoRemainingTan(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        $total = 0.0;
        PurchaseOrder::query()
            ->where('type', \App\Support\Purchase\PurchaseOrderType::GREIGE)
            ->whereIn('status', [
                \App\Support\Purchase\PurchaseOrderStatus::ORDERED,
                \App\Support\Purchase\PurchaseOrderStatus::PARTIAL,
            ])
            ->with(['lines.greige'])
            ->each(function (PurchaseOrder $po) use ($greigeSku, &$total) {
                foreach ($po->lines as $line) {
                    if (($line->greige?->sku ?? '') !== $greigeSku) {
                        continue;
                    }
                    $line->setRelation('purchaseOrder', $po);
                    $total += $line->remainingTan();
                }
            });

        return round($total, QtyHelper::TAN_DECIMALS);
    }

    /**
     * 製品発注に使える生機の反数（染工場在庫 + 生機発注残）を返す。
     *
     * 単位は生機反。製品反との比較は shortageTan() 側で換算する。
     *
     * @param  string  $greigeSku  生機 SKU。在庫・発注残の品番絞り込みに使う
     * @param  int|null  $excludeProductPoId  製品発注ID。greigePoRemainingTan へそのまま渡す（未使用）
     * @return float  利用可能な生機反数
     */
    public static function availableTan(string $greigeSku, ?int $excludeProductPoId = null): float
    {
        return round(
            self::dyeFactoryTan($greigeSku) + self::greigePoRemainingTan($greigeSku, $excludeProductPoId),
            QtyHelper::TAN_DECIMALS
        );
    }

    /**
     * 製品発注の必要反数を、利用可能な生機（製品反換算）で賄えるかを返す。
     *
     * shortageTan() と同じ判定を使う。警告フラグと不足メッセージがずれないようにするため。
     *
     * @param  int  $productId  製品マスタ ID。生機 SKU の特定と換算に使う
     * @param  float  $requiredTan  製品の発注反数
     * @param  int|null  $excludeProductPoId  製品発注ID。shortageTan へそのまま渡す（未使用）
     * @return bool  足りる（メッセージなし）なら true。品番なし・不足なら false
     *
     * @see shortageTan()
     */
    public static function canFulfillProductTan(int $productId, float $requiredTan, ?int $excludeProductPoId = null): bool
    {
        return self::shortageTan($productId, $requiredTan, $excludeProductPoId) === null;
    }

    /**
     * 製品発注の必要反数に対する生機不足メッセージを返す。足りていれば null。
     *
     * 処理の流れ:
     * 1. 製品に紐づく生機品番を探す。無ければエラー文を返す
     * 2. 生機の利用可能反を、同じ長さの製品反へ換算する
     * 3. 必要反以下なら null、足りなければ不足反数つきのメッセージを返す
     *
     * @param  int  $productId  製品マスタ ID。生機 SKU の特定と m/反換算に使う
     * @param  float  $requiredTan  製品の発注反数（0.25反刻みの入力値）
     * @param  int|null  $excludeProductPoId  製品発注ID。availableTan へそのまま渡す（未使用）
     * @return string|null  不足時の日本語メッセージ。足りるとき・必要反が 0 以下のときは null
     *
     * @see QtyHelper::productTanFromGreigeTan()
     */
    public static function shortageTan(int $productId, float $requiredTan, ?int $excludeProductPoId = null): ?string
    {
        $sku = self::greigeSkuForProduct($productId);
        if ($sku === null) {
            return '製品に紐づく生機品番が見つかりません。';
        }

        if ($requiredTan <= 0) {
            return null;
        }

        $availableGreigeTan = self::availableTan($sku, $excludeProductPoId);
        $availableProductTan = QtyHelper::productTanFromGreigeTan($availableGreigeTan, $productId);
        if ($availableProductTan >= $requiredTan) {
            return null;
        }

        $short = $requiredTan - $availableProductTan;
        $greige = \App\Support\Master\MasterCatalog::findGreige($sku);

        return ($greige?->name ?? $sku).'（'.$sku.'）が '.QtyHelper::formatTanCount($short).'反 不足しています（必要 '.QtyHelper::formatTanCount($requiredTan).'反 / 利用可能 '.QtyHelper::formatTanCount($availableProductTan).'反）。';
    }
}
