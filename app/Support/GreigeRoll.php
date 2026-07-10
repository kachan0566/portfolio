<?php

namespace App\Support;

use App\Models\GreigeRoll as GreigeRollModel;
use App\Models\PurchaseOrderLine;
use App\Models\ReceivingLine;
use Illuminate\Support\Collection;

/**
 * 生機の物理反（1在庫単位 = 1レコード）。
 * DB（greige_rolls）が投入済みなら DB を正とし、未投入時は JSON ファイルに保存する。
 */
class GreigeRoll
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_PARTIALLY_CONSUMED = 'partially_consumed';

    public const STATUS_CONSUMED = 'consumed';

    public const FILE = 'greige_rolls.json';

    public const BOOTSTRAP_FLAG = 'greige_rolls_bootstrapped.flag';

    /** @var list<array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @internal テスト用にメモリキャッシュを破棄する */
    public static function resetCacheForTesting(): void
    {
        self::$cache = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        if (DemoData::usesGreigeRollDatabase()) {
            return GreigeRollModel::query()
                ->with(['greige', 'receivingLine'])
                ->orderBy('id')
                ->get()
                ->map(fn (GreigeRollModel $roll) => self::normalizeRoll($roll->toSupportArray()))
                ->values()
                ->all();
        }

        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = storage_path('app/'.self::FILE);
        if (! is_file($path)) {
            return self::$cache = [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['rolls']) || ! is_array($data['rolls'])) {
            return self::$cache = [];
        }

        return self::$cache = self::normalizeRolls($data['rolls']);
    }

    /** @return Collection<int, object> */
    public static function inStockForSku(string $greigeSku): Collection
    {
        return collect(self::all())
            ->filter(fn ($roll) => (string) ($roll['greige_sku'] ?? '') === $greigeSku
                && in_array((string) ($roll['status'] ?? ''), [self::STATUS_IN_STOCK, self::STATUS_PARTIALLY_CONSUMED], true))
            ->sortBy([
                ['received_date', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn ($roll) => (object) $roll)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function forPo(int $poId, ?string $status = null): Collection
    {
        return collect(self::all())
            ->filter(function ($roll) use ($poId, $status) {
                if ((int) ($roll['purchase_order_id'] ?? 0) !== $poId) {
                    return false;
                }
                if ($status === null) {
                    return true;
                }

                return (string) ($roll['status'] ?? '') === $status;
            })
            ->sortBy('id')
            ->map(fn ($roll) => (object) $roll)
            ->values();
    }

    public static function find(int $id): ?object
    {
        $roll = collect(self::all())->firstWhere('id', $id);

        return $roll !== null ? (object) $roll : null;
    }

    public static function stockTanForSku(string $greigeSku): float
    {
        return round((float) self::inStockForSku($greigeSku)->sum('tan_qty'), 2);
    }

    public static function stockMetersForSku(string $greigeSku): float
    {
        return round((float) self::inStockForSku($greigeSku)->sum('actual_qty_m'), 2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): object
    {
        if (DemoData::usesGreigeRollDatabase()) {
            $greigeSku = (string) ($attributes['greige_sku'] ?? '');
            $greige = DemoData::findGreige($greigeSku);
            $receivingLineId = self::resolveReceivingLineId(
                (int) ($attributes['receiving_id'] ?? 0),
                (int) ($attributes['purchase_order_id'] ?? 0),
            );

            if ($greige !== null && $receivingLineId > 0) {
                $roll = GreigeRollModel::query()->create([
                'code' => (string) ($attributes['code'] ?? ''),
                'greige_id' => $greige?->id,
                'purchase_order_id' => $attributes['purchase_order_id'] ?? null,
                'receiving_line_id' => $receivingLineId,
                'tan_qty' => QtyHelper::roundReceivingTan((float) ($attributes['tan_qty'] ?? 1.0)),
                'actual_qty_m' => round((float) ($attributes['actual_qty_m'] ?? 0), 2),
                'nominal_meters' => (int) ($attributes['nominal_meters'] ?? DemoData::METERS_PER_TAN_GREIGE),
                'status' => (string) ($attributes['status'] ?? self::STATUS_IN_STOCK),
                'received_date' => (string) ($attributes['received_date'] ?? date('Y-m-d')),
                ]);

                $roll->load(['greige', 'receivingLine']);

                return (object) self::normalizeRoll($roll->toSupportArray());
            }
        }

        $rolls = self::all();
        $nextId = (int) (collect($rolls)->max('id') ?? 0) + 1;

        $roll = self::normalizeRoll(array_merge([
            'id' => $nextId,
            'code' => 'GR-'.$nextId,
            'greige_sku' => '',
            'purchase_order_id' => null,
            'receiving_id' => null,
            'tan_qty' => 1.0,
            'actual_qty_m' => 0.0,
            'nominal_meters' => DemoData::METERS_PER_TAN_GREIGE,
            'status' => self::STATUS_IN_STOCK,
            'received_date' => date('Y-m-d'),
        ], $attributes));

        $rolls[] = $roll;
        self::persist($rolls);

        return (object) $roll;
    }

    public static function update(int $id, array $attributes): ?object
    {
        if (DemoData::usesGreigeRollDatabase()) {
            $roll = GreigeRollModel::query()->with(['greige', 'receivingLine'])->find($id);
            if ($roll === null) {
                return null;
            }

            $payload = [];
            foreach (['code', 'purchase_order_id', 'tan_qty', 'actual_qty_m', 'nominal_meters', 'status', 'received_date'] as $key) {
                if (array_key_exists($key, $attributes)) {
                    $payload[$key] = $attributes[$key];
                }
            }

            if (isset($attributes['greige_sku'])) {
                $greige = DemoData::findGreige((string) $attributes['greige_sku']);
                if ($greige !== null) {
                    $payload['greige_id'] = $greige->id;
                }
            }

            if (isset($attributes['receiving_id']) || isset($attributes['purchase_order_id'])) {
                $receivingLineId = self::resolveReceivingLineId(
                    (int) ($attributes['receiving_id'] ?? $roll->receivingLine?->receiving_id ?? 0),
                    (int) ($attributes['purchase_order_id'] ?? $roll->purchase_order_id ?? 0),
                );
                if ($receivingLineId > 0) {
                    $payload['receiving_line_id'] = $receivingLineId;
                }
            }

            $roll->update($payload);
            $roll->load(['greige', 'receivingLine']);

            return (object) self::normalizeRoll($roll->fresh(['greige', 'receivingLine'])->toSupportArray());
        }

        $rolls = self::all();
        $updated = null;

        foreach ($rolls as &$roll) {
            if ((int) $roll['id'] !== $id) {
                continue;
            }
            $roll = self::normalizeRoll(array_merge($roll, $attributes));
            $updated = (object) $roll;
            break;
        }
        unset($roll);

        if ($updated !== null) {
            self::persist($rolls);
        }

        return $updated;
    }

    /**
     * @param  list<array<string, mixed>>  $rolls
     */
    public static function replaceAll(array $rolls): void
    {
        if (DemoData::usesGreigeRollDatabase()) {
            GreigeRollModel::query()->delete();
            foreach (self::normalizeRolls($rolls) as $roll) {
                self::create($roll);
            }

            return;
        }

        self::persist(self::normalizeRolls($rolls));
    }

    public static function resetBootstrap(): void
    {
        $flag = storage_path('app/'.self::BOOTSTRAP_FLAG);
        $file = storage_path('app/'.self::FILE);
        if (is_file($flag)) {
            unlink($flag);
        }
        if (is_file($file)) {
            unlink($file);
        }
        self::$cache = null;
    }

    /**
     * @param  list<array<string, mixed>>  $rolls
     */
    private static function persist(array $rolls): void
    {
        $path = storage_path('app/'.self::FILE);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['rolls' => self::normalizeRolls($rolls)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        self::$cache = null;
    }

    /**
     * @param  list<array<mixed>>  $rolls
     * @return list<array<string, mixed>>
     */
    private static function normalizeRolls(array $rolls): array
    {
        return array_values(array_map(fn ($roll) => self::normalizeRoll((array) $roll), $rolls));
    }

    /**
     * @param  array<string, mixed>  $roll
     * @return array<string, mixed>
     */
    private static function normalizeRoll(array $roll): array
    {
        return [
            'id' => (int) ($roll['id'] ?? 0),
            'code' => (string) ($roll['code'] ?? ''),
            'greige_sku' => (string) ($roll['greige_sku'] ?? ''),
            'purchase_order_id' => isset($roll['purchase_order_id']) ? (int) $roll['purchase_order_id'] : null,
            'receiving_id' => isset($roll['receiving_id']) ? (int) $roll['receiving_id'] : null,
            'tan_qty' => QtyHelper::roundReceivingTan((float) ($roll['tan_qty'] ?? 1.0)),
            'actual_qty_m' => round((float) ($roll['actual_qty_m'] ?? 0), 2),
            'nominal_meters' => (int) ($roll['nominal_meters'] ?? DemoData::METERS_PER_TAN_GREIGE),
            'status' => (string) ($roll['status'] ?? self::STATUS_IN_STOCK),
            'received_date' => (string) ($roll['received_date'] ?? date('Y-m-d')),
        ];
    }

    private static function resolveReceivingLineId(int $receivingId, int $purchaseOrderId): int
    {
        if ($receivingId > 0) {
            $line = ReceivingLine::query()->where('receiving_id', $receivingId)->orderBy('line_no')->first();
            if ($line !== null) {
                return (int) $line->id;
            }
        }

        if ($purchaseOrderId > 0) {
            $poLine = PurchaseOrderLine::query()
                ->where('purchase_order_id', $purchaseOrderId)
                ->orderBy('line_no')
                ->first();
            if ($poLine !== null) {
                $line = ReceivingLine::query()
                    ->where('purchase_order_line_id', $poLine->id)
                    ->orderBy('line_no')
                    ->first();
                if ($line !== null) {
                    return (int) $line->id;
                }
            }
        }

        return 0;
    }
}
