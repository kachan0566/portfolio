<?php

namespace App\Support;

use App\Support\MasterCatalog;

use App\Models\Greige;
use App\Models\Product;
use App\Models\PurchaseOrder;

/**
 * 製品発注向け：生機の供給量（染工場仕掛 + 生機発注残）の判定。
 */
class GreigeSupply
{
    public static function greigeSkuForProduct(int $productId): ?string
    {
        return MasterCatalog::findProduct($productId)?->greige_sku;
    }

    /** 染工場仕掛の生機（m）— 生機発注入荷実績 */
    public static function dyeFactoryMeters(string $greigeSku): int
    {
        return GreigeInventory::totalMetersForSku($greigeSku);
    }

    /** 生機発注の未入荷残（m） */
    public static function greigePoRemainingMeters(string $greigeSku, ?int $excludeProductPoId = null): int
    {
        $total = 0;
        PurchaseOrder::query()
            ->where('type', PurchaseOrderType::GREIGE)
            ->whereIn('status', [
                PurchaseOrderStatus::ORDERED,
                PurchaseOrderStatus::PARTIAL,
            ])
            ->with(['lines.greige'])
            ->each(function (PurchaseOrder $po) use ($greigeSku, &$total) {
                foreach ($po->lines as $line) {
                    if (($line->greige?->sku ?? '') !== $greigeSku) {
                        continue;
                    }
                    $ordered = (int) ($line->qty_meters ?? 0);
                    $received = (int) ($line->received_qty_m ?? 0);
                    $total += max(0, $ordered - $received);
                }
            });

        return $total;
    }

    public static function availableMeters(string $greigeSku, ?int $excludeProductPoId = null): int
    {
        return self::dyeFactoryMeters($greigeSku) + self::greigePoRemainingMeters($greigeSku, $excludeProductPoId);
    }

    public static function canFulfillProductMeters(int $productId, int $requiredMeters, ?int $excludeProductPoId = null): bool
    {
        $sku = self::greigeSkuForProduct($productId);
        if ($sku === null || $requiredMeters <= 0) {
            return false;
        }

        return self::availableMeters($sku, $excludeProductPoId) >= $requiredMeters;
    }

    public static function shortageMessage(int $productId, int $requiredMeters, ?int $excludeProductPoId = null): ?string
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
        $greige = MasterCatalog::findGreige($sku);

        return ($greige?->name ?? $sku).'（'.$sku.'）が '.number_format($short).'m 不足しています（必要 '.number_format($requiredMeters).'m / 利用可能 '.number_format($available).'m）。';
    }
}
