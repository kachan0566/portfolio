<?php

namespace App\Support;

/**
 * 発注種別ごとのステータス（内部キーと日本語ラベル）。
 */
class PurchaseOrderStatus
{
    public const DRAFT = 'draft';

    public const ORDERED = 'ordered';

    public const PARTIAL = 'partial';

    public const RECEIVED = 'received';

    public const CANCELLED = 'cancelled';

    /** @return array<string, string> key => 日本語ラベル */
    public static function labelsFor(string $type): array
    {
        $base = [
            self::DRAFT => '下書き',
            self::ORDERED => '発注済',
            self::PARTIAL => '一部入荷',
            self::RECEIVED => '入荷完了',
            self::CANCELLED => 'キャンセル',
        ];

        return match ($type) {
            PurchaseOrderType::YARN => $base,
            PurchaseOrderType::GREIGE => $base,
            PurchaseOrderType::PRODUCT => $base,
            default => $base,
        };
    }

    /** @return list<string> */
    public static function keysFor(string $type): array
    {
        return array_keys(self::labelsFor($type));
    }

    public static function label(string $type, string $status): string
    {
        return self::labelsFor($type)[$status] ?? $status;
    }

    public static function isActive(string $status): bool
    {
        return ! in_array($status, [self::RECEIVED, self::CANCELLED], true);
    }
}
