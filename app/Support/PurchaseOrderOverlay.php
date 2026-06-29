<?php

namespace App\Support;

/**
 * デモ用：発注の追加・更新をセッションに保持する。
 */
class PurchaseOrderOverlay
{
    private const ADDITIONS = 'demo.po_additions';

    private const OVERRIDES = 'demo.po_overrides';

    /** @return list<array<string, mixed>> */
    public static function additions(): array
    {
        return session(self::ADDITIONS, []);
    }

    /** @param array<string, mixed> $row */
    public static function add(array $row): void
    {
        $items = self::additions();
        $items[] = $row;
        session([self::ADDITIONS => $items]);
    }

    /** @return array<string, mixed> */
    public static function overrides(int $poId): array
    {
        $all = session(self::OVERRIDES, []);

        return is_array($all[$poId] ?? null) ? $all[$poId] : [];
    }

    /** @param array<string, mixed> $patch */
    public static function patch(int $poId, array $patch): void
    {
        $all = session(self::OVERRIDES, []);
        $all[$poId] = array_merge($all[$poId] ?? [], $patch);
        session([self::OVERRIDES => $all]);
    }

    public static function nextId(): int
    {
        $max = DemoData::basePurchaseOrderRows()->max('id') ?? 0;
        foreach (self::additions() as $row) {
            $max = max($max, (int) ($row['id'] ?? 0));
        }

        return $max + 1;
    }

    public static function clear(): void
    {
        session()->forget([self::ADDITIONS, self::OVERRIDES]);
    }
}
