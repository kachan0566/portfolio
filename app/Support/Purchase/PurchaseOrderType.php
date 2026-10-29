<?php

namespace App\Support\Purchase;

/**
 * 発注種別（糸・生機・製品）。
 */
class PurchaseOrderType
{
    public const YARN = 'yarn';

    public const GREIGE = 'greige';

    public const PRODUCT = 'product';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::YARN, self::GREIGE, self::PRODUCT];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::YARN => '糸発注',
            self::GREIGE => '生機発注',
            self::PRODUCT => '製品発注',
            default => $type,
        };
    }

    /** 依頼先（仕入先）として選べる種別 */
    /** @return list<string> */
    public static function supplierTypesFor(string $type): array
    {
        return match ($type) {
            self::YARN => [\App\Support\Master\SupplierType::SPINNING],
            self::GREIGE => [\App\Support\Master\SupplierType::WEAVING],
            self::PRODUCT => [\App\Support\Master\SupplierType::DYEING],
            default => [],
        };
    }

    /** 出荷先として選べる場所種別 */
    /** @return list<string> */
    public static function shipToTypesFor(string $type): array
    {
        return match ($type) {
            self::YARN => [\App\Support\Master\ShipToType::WEAVING],
            self::GREIGE => [\App\Support\Master\ShipToType::DYEING],
            self::PRODUCT => [\App\Support\Master\ShipToType::DYEING, \App\Support\Master\ShipToType::WAREHOUSE],
            default => [],
        };
    }
}
