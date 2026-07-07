<?php

namespace App\Support;

/**
 * デモ用：受注の追加・更新をセッションに保持する。
 */
class OrderOverlay
{
    private const ADDITIONS = 'demo.order_additions';

    private const OVERRIDES = 'demo.order_overrides';

    /** @return list<array<string, mixed>> */
    public static function additions(): array
    {
        return session(self::ADDITIONS, []);
    }

    /** @param  array<string, mixed>  $row */
    public static function add(array $row): void
    {
        $items = self::additions();
        $items[] = $row;
        session([self::ADDITIONS => $items]);
    }

    /** @return array<string, mixed> */
    public static function overrides(int $orderId): array
    {
        $all = session(self::OVERRIDES, []);

        return is_array($all[$orderId] ?? null) ? $all[$orderId] : [];
    }

    /** @param  array<string, mixed>  $patch */
    public static function patch(int $orderId, array $patch): void
    {
        $all = session(self::OVERRIDES, []);
        $all[$orderId] = array_merge($all[$orderId] ?? [], $patch);
        session([self::OVERRIDES => $all]);
    }

    public static function nextId(): int
    {
        $max = 10;
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
