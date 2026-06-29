<?php

namespace App\Support;

/**
 * 出荷先（納入先）の場所種別。
 */
class ShipToType
{
    public const WEAVING = 'weaving';

    public const DYEING = 'dyeing';

    public const WAREHOUSE = 'warehouse';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::WEAVING => '織工場',
            self::DYEING => '染工場',
            self::WAREHOUSE => '倉庫',
        ];
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }
}
