<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * 反明細（1物理反 = 1レコード）。各工程の実測 m を保持する。
 *
 * Phase 3: 織り上がり（weaving_meters）・染め上がり（dyeing_meters）で誤差を記録。
 * 集計の反数は常に 1.0（物理反単位）。業務上の反数（0.05刻み）は別レイヤー。
 */
class FabricTanRoll
{
    public const STAGE_GREIGE_WIP = 'greige_wip';

    public const STAGE_PRODUCT = 'product_stock';

    public const STAGE_CONSUMED = 'consumed';

    public const FILE = 'fabric_tan_rolls.json';

    public const BOOTSTRAP_FLAG = 'fabric_tan_rolls_bootstrapped.flag';

    /** @var list<array<string, mixed>>|null */
    private static ?array $cache = null;

    private static bool $bootstrapping = false;

    public static function ensureBootstrapped(): void
    {
        if (self::$bootstrapping) {
            return;
        }

        $flag = storage_path('app/'.self::BOOTSTRAP_FLAG);
        if (is_file($flag)) {
            return;
        }

        self::$bootstrapping = true;
        \App\Services\Fabric\TanRollBootstrap::run();
        self::$bootstrapping = false;

        $dir = dirname($flag);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($flag, date('c'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        self::ensureBootstrapped();

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
    public static function forPo(int $poId): Collection
    {
        return collect(self::all())
            ->filter(fn ($roll) => (int) ($roll['po_id'] ?? 0) === $poId)
            ->sortBy('id')
            ->map(fn ($roll) => (object) $roll)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function forGreigeSku(string $greigeSku, ?string $stage = self::STAGE_GREIGE_WIP): Collection
    {
        return collect(self::all())
            ->filter(function ($roll) use ($greigeSku, $stage) {
                if ((string) ($roll['greige_sku'] ?? '') !== $greigeSku) {
                    return false;
                }
                if ($stage === null) {
                    return true;
                }

                return (string) ($roll['stage'] ?? '') === $stage;
            })
            ->sortBy('id')
            ->map(fn ($roll) => (object) $roll)
            ->values();
    }

    /** @return Collection<int, object> */
    public static function forProduct(int $productId, ?string $stage = self::STAGE_PRODUCT): Collection
    {
        return collect(self::all())
            ->filter(function ($roll) use ($productId, $stage) {
                if ((int) ($roll['product_id'] ?? 0) !== $productId) {
                    return false;
                }
                if ($stage === null) {
                    return true;
                }

                return (string) ($roll['stage'] ?? '') === $stage;
            })
            ->sortBy('id')
            ->map(fn ($roll) => (object) $roll)
            ->values();
    }

    public static function actualMeters(object|array $roll): float
    {
        $row = (object) $roll;
        if (isset($row->dyeing_meters) && $row->dyeing_meters !== null) {
            return round((float) $row->dyeing_meters, 2);
        }

        return round((float) ($row->weaving_meters ?? 0), 2);
    }

    public static function varianceMeters(object|array $roll): float
    {
        $row = (object) $roll;
        $nominal = (int) ($row->nominal_meters ?? 0);
        $actual = self::actualMeters($row);

        return round($actual - $nominal, 2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function create(array $attributes): object
    {
        $rolls = self::all();
        $nextId = (int) (collect($rolls)->max('id') ?? 0) + 1;

        $roll = self::normalizeRoll(array_merge([
            'id' => $nextId,
            'code' => 'ROLL-'.$nextId,
            'po_id' => 0,
            'greige_sku' => '',
            'product_id' => null,
            'parent_roll_id' => null,
            'stage' => self::STAGE_GREIGE_WIP,
            'nominal_meters' => DemoData::METERS_PER_TAN_GREIGE,
            'weaving_meters' => 0.0,
            'dyeing_meters' => null,
            'tan_qty' => 1.0,
            'measured_at' => date('Y-m-d'),
            'weaving_measured_at' => null,
            'dyeing_measured_at' => null,
        ], $attributes));

        $rolls[] = $roll;
        self::persist($rolls);

        return (object) $roll;
    }

    /**
     * @param  list<array<string, mixed>>  $rolls
     */
    public static function replaceAll(array $rolls): void
    {
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
        self::$bootstrapping = false;
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
        $nominal = (int) ($roll['nominal_meters'] ?? DemoData::METERS_PER_TAN_GREIGE);
        $weaving = round((float) ($roll['weaving_meters'] ?? 0), 2);
        $dyeing = array_key_exists('dyeing_meters', $roll) && $roll['dyeing_meters'] !== null
            ? round((float) $roll['dyeing_meters'], 2)
            : null;

        return [
            'id' => (int) ($roll['id'] ?? 0),
            'code' => (string) ($roll['code'] ?? ''),
            'po_id' => (int) ($roll['po_id'] ?? 0),
            'greige_sku' => (string) ($roll['greige_sku'] ?? ''),
            'product_id' => isset($roll['product_id']) ? (int) $roll['product_id'] : null,
            'parent_roll_id' => isset($roll['parent_roll_id']) ? (int) $roll['parent_roll_id'] : null,
            'stage' => (string) ($roll['stage'] ?? self::STAGE_GREIGE_WIP),
            'nominal_meters' => $nominal,
            'weaving_meters' => $weaving,
            'dyeing_meters' => $dyeing,
            'tan_qty' => 1.0,
            'measured_at' => (string) ($roll['measured_at'] ?? date('Y-m-d')),
            'weaving_measured_at' => $roll['weaving_measured_at'] ?? null,
            'dyeing_measured_at' => $roll['dyeing_measured_at'] ?? null,
        ];
    }
}
