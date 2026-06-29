<?php

namespace App\Support;

/**
 * 仕入先（依頼先）の種別。
 */
class SupplierType
{
    public const SPINNING = 'spinning';

    public const CHEMICAL = 'chemical';

    public const DYE = 'dye';

    public const WEAVING = 'weaving';

    public const DYEING = 'dyeing';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::SPINNING => '紡績',
            self::CHEMICAL => '化学',
            self::DYE => '染料',
            self::WEAVING => '織編',
            self::DYEING => '染色',
        ];
    }

    public static function label(string $type): string
    {
        return self::labels()[$type] ?? $type;
    }
}
