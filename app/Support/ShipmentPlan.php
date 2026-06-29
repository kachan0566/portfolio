<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 出荷予定／出荷確定（デモ用 JSON）。
 */
class ShipmentPlan
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    private const FILE = 'shipment_plans.json';

    /** @var list<array<string, mixed>>|null */
    private static ?array $cache = null;

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return self::$cache = self::seedDefaults();
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['plans']) || ! is_array($data['plans'])) {
            return self::$cache = self::seedDefaults();
        }

        return self::$cache = self::normalizePlans($data['plans']);
    }

    /**
     * @return Collection<int, object>
     */
    public static function forProduct(int $productId): Collection
    {
        return collect(self::all())
            ->filter(fn ($p) => (int) ($p['product_id'] ?? 0) === $productId)
            ->map(fn ($p) => self::enrich((object) $p))
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public static function forOrder(int $orderId): Collection
    {
        return collect(self::all())
            ->filter(fn ($p) => (int) ($p['order_id'] ?? 0) === $orderId)
            ->map(fn ($p) => self::enrich((object) $p))
            ->values();
    }

    public static function find(int $id): ?object
    {
        $plan = collect(self::all())->firstWhere('id', $id);

        return $plan ? self::enrich((object) $plan) : null;
    }

    public static function unshippedQty(object $plan): float
    {
        return max(0.0, round((float) $plan->confirmed_qty_m - (float) $plan->shipped_qty_m, 2));
    }

    public static function isActiveForForecast(object $plan): bool
    {
        return in_array($plan->status, [self::STATUS_CONFIRMED, self::STATUS_PARTIAL], true)
            && self::unshippedQty($plan) > 0;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): object
    {
        $plans = self::all();
        $nextId = (int) (collect($plans)->max('id') ?? 0) + 1;

        $plan = self::normalizePlan(array_merge([
            'id' => $nextId,
            'code' => 'SP-'.date('ym').'-'.str_pad((string) $nextId, 3, '0', STR_PAD_LEFT),
            'order_id' => 0,
            'product_id' => 0,
            'planned_ship_date' => date('Y-m-d'),
            'confirmed_qty_m' => 0.0,
            'shipped_qty_m' => 0.0,
            'status' => self::STATUS_CONFIRMED,
            'note' => '',
            'created_by' => '木村 勝也',
        ], $attributes));

        $plans[] = $plan;
        self::persist($plans);

        return self::enrich((object) $plan);
    }

    public static function recordShipment(int $orderId, float $qtyM): void
    {
        if ($qtyM <= 0) {
            return;
        }

        $plans = self::all();
        $remaining = $qtyM;

        $orderPlans = collect($plans)
            ->filter(fn ($p) => (int) ($p['order_id'] ?? 0) === $orderId)
            ->filter(fn ($p) => in_array($p['status'], [self::STATUS_CONFIRMED, self::STATUS_PARTIAL], true))
            ->sortBy([
                ['planned_ship_date', 'asc'],
                ['id', 'asc'],
            ]);

        foreach ($orderPlans as $plan) {
            if ($remaining <= 0) {
                break;
            }
            $unshipped = max(0.0, (float) $plan['confirmed_qty_m'] - (float) $plan['shipped_qty_m']);
            if ($unshipped <= 0) {
                continue;
            }
            $take = min($unshipped, $remaining);
            foreach ($plans as $i => $p) {
                if ((int) $p['id'] !== (int) $plan['id']) {
                    continue;
                }
                $plans[$i]['shipped_qty_m'] = round((float) $p['shipped_qty_m'] + $take, 2);
                $plans[$i]['status'] = $plans[$i]['shipped_qty_m'] >= (float) $p['confirmed_qty_m']
                    ? self::STATUS_COMPLETED
                    : self::STATUS_PARTIAL;
                $plans[$i] = self::normalizePlan($plans[$i]);
                break;
            }
            $remaining -= $take;
        }

        self::persist($plans);
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    public static function replaceAll(array $plans): void
    {
        self::persist(self::normalizePlans($plans));
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    public static function enrich(object $plan): object
    {
        $order = DemoData::orders()->firstWhere('id', (int) $plan->order_id);
        $product = DemoData::findProduct((int) $plan->product_id);
        $plan->order_code = $order?->code ?? '—';
        $plan->sku = $product?->sku ?? '—';
        $plan->unshipped_qty_m = self::unshippedQty($plan);
        $plan->status_label = match ($plan->status) {
            self::STATUS_CONFIRMED => '出荷確定',
            self::STATUS_PARTIAL => '一部出荷',
            self::STATUS_COMPLETED => '出荷完了',
            self::STATUS_CANCELLED => 'キャンセル',
            default => $plan->status,
        };

        return $plan;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function seedDefaults(): array
    {
        $plans = [
            ['id' => 1, 'code' => 'SP-2606-001', 'order_id' => 2, 'product_id' => 3, 'planned_ship_date' => '2026-06-17', 'confirmed_qty_m' => 120, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => '分納2回目'],
            ['id' => 2, 'code' => 'SP-2606-002', 'order_id' => 3, 'product_id' => 5, 'planned_ship_date' => '2026-06-19', 'confirmed_qty_m' => 90, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => ''],
            ['id' => 3, 'code' => 'SP-2606-003', 'order_id' => 5, 'product_id' => 4, 'planned_ship_date' => '2026-06-24', 'confirmed_qty_m' => 150, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => '入荷待ち'],
            ['id' => 4, 'code' => 'SP-2606-004', 'order_id' => 6, 'product_id' => 1, 'planned_ship_date' => '2026-06-27', 'confirmed_qty_m' => 60, 'shipped_qty_m' => 0, 'status' => self::STATUS_CONFIRMED, 'note' => '残量出荷'],
            ['id' => 5, 'code' => 'SP-2606-005', 'order_id' => 2, 'product_id' => 3, 'planned_ship_date' => '2026-06-14', 'confirmed_qty_m' => 80, 'shipped_qty_m' => 80, 'status' => self::STATUS_COMPLETED, 'note' => '1回目出荷済'],
        ];

        $normalized = self::normalizePlans($plans);
        self::persist($normalized);

        return self::$cache = $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     */
    private static function persist(array $plans): void
    {
        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['plans' => self::normalizePlans($plans)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$cache = null;
    }

    /**
     * @param  list<array<mixed>>  $plans
     * @return list<array<string, mixed>>
     */
    private static function normalizePlans(array $plans): array
    {
        return array_values(array_map(fn ($p) => self::normalizePlan((array) $p), $plans));
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private static function normalizePlan(array $plan): array
    {
        $confirmed = round((float) ($plan['confirmed_qty_m'] ?? 0), 2);
        $shipped = round((float) ($plan['shipped_qty_m'] ?? 0), 2);

        return [
            'id' => (int) ($plan['id'] ?? 0),
            'code' => (string) ($plan['code'] ?? ''),
            'order_id' => (int) ($plan['order_id'] ?? 0),
            'product_id' => (int) ($plan['product_id'] ?? 0),
            'planned_ship_date' => (string) ($plan['planned_ship_date'] ?? date('Y-m-d')),
            'confirmed_qty_m' => $confirmed,
            'shipped_qty_m' => min($shipped, $confirmed),
            'status' => (string) ($plan['status'] ?? self::STATUS_CONFIRMED),
            'note' => (string) ($plan['note'] ?? ''),
            'created_by' => (string) ($plan['created_by'] ?? '木村 勝也'),
        ];
    }
}
