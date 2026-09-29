<?php

namespace App\Models;

use App\Support\BusinessDate;
use App\Support\DemoData;
use App\Support\FabricQuantity;
use App\Support\QtyHelper;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * 受注数量・出荷済み数量と、得意先・製品との関連を保持する。
 */
#[Fillable([
    'code',
    'customer_id',
    'product_id',
    'order_qty_mode',
    'qty_tan',
    'qty_meters',
    'shipped_qty_tan',
    'shipped_qty_m',
    'order_date',
    'due_date',
    'planned_ship_date',
    'ship_memo',
])]
class Order extends Model
{
    /**
     * 0.25反と小数mを失わず読み書きする型変換を定義する。
     *
     * @return array<string, string> Eloquentの列別キャスト
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'qty_tan' => 'decimal:2',
            'qty_meters' => 'decimal:2',
            'shipped_qty_tan' => 'decimal:2',
            'shipped_qty_m' => 'decimal:2',
            'order_date' => 'date',
            'due_date' => 'date',
            'planned_ship_date' => 'date',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<OrderAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(OrderAllocation::class);
    }

    /** @return Collection<int, object> */
    public static function displayList(): Collection
    {
        return self::query()
            ->with(['customer', 'product'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (self $order) => $order->toDisplayObject());
    }

    public static function findForDisplay(int $id): ?object
    {
        $order = self::query()->with(['customer', 'product'])->find($id);

        return $order?->toDisplayObject();
    }

    /**
     * 保存mが反数の標準換算mと異なるかを判定する。
     *
     * @return bool m指定受注または標準換算との差が0.001mを超える場合はtrue
     */
    public function metersOverridden(): bool
    {
        if (($this->order_qty_mode ?? 'tan') === 'meters') {
            return true;
        }

        if ($this->qty_tan <= 0 || $this->qty_meters <= 0) {
            return false;
        }

        $nominal = QtyHelper::metersFromTan($this->qty_tan, (int) $this->product_id);

        return abs((float) $this->qty_meters - $nominal) > 0.001;
    }

    /**
     * 出荷済み実測mを小数2桁で返す。
     *
     * @return float 0以上の出荷済みm
     */
    public function shippedMeters(): float
    {
        return max(0.0, round((float) ($this->shipped_qty_m ?? 0), 2));
    }

    public function shippedTan(): float
    {
        return max(0.0, QtyHelper::roundReceivingTan((float) ($this->shipped_qty_tan ?? 0)));
    }

    /**
     * 受注数量から出荷済み数量を引いた受注残mを返す。
     *
     * @return float 0以上の受注残m（小数2桁）
     */
    public function remainingMeters(): float
    {
        $mode = $this->order_qty_mode ?? 'tan';

        if ($mode === 'meters') {
            $qtyM = (float) ($this->qty_meters ?? $this->qty ?? 0);

            return max(0.0, round($qtyM - $this->shippedMeters(), 2));
        }

        return QtyHelper::metersFromTan($this->remainingTan(), (int) $this->product_id);
    }

    /**
     * 受注数量から出荷済み数量を引いた受注残反数を返す。
     *
     * @return float 反数指定は0.25刻み、m指定は出荷に必要な切り上げ反数
     */
    public function remainingTan(): float
    {
        $mode = $this->order_qty_mode ?? 'tan';

        if ($mode === 'meters') {
            $remainingM = $this->remainingMeters();
            if ($remainingM <= 0) {
                return 0.0;
            }

            return QtyHelper::tanCountCeilForShipment($remainingM, (int) $this->product_id);
        }

        $qtyTan = (float) ($this->qty_tan ?? QtyHelper::roundTan(
            QtyHelper::tanCount((float) $this->qty, (int) $this->product_id)
        ));

        return max(0.0, QtyHelper::roundReceivingTan($qtyTan - $this->shippedTan()));
    }

    /** @return float 受注残m（小数2桁） */
    public function remaining(): float
    {
        return $this->remainingMeters();
    }

    /**
     * 指定受注の出荷済み実測mを返す。
     *
     * @param  int  $orderId  orders.id
     * @return float 受注がない場合は0、ある場合は出荷済みm
     */
    public static function shippedMetersFor(int $orderId): float
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        return self::query()->find($orderId)?->shippedMeters() ?? 0;
    }

    public static function shippedTanFor(int $orderId): float
    {
        if (! Schema::hasTable('orders')) {
            return 0.0;
        }

        return self::query()->find($orderId)?->shippedTan() ?? 0.0;
    }

    /**
     * 指定受注の受注残mを返す。
     *
     * @param  int  $orderId  orders.id
     * @return float 受注がない場合は0、ある場合は受注残m
     */
    public static function remainingMetersFor(int $orderId): float
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        return self::query()->find($orderId)?->remainingMeters() ?? 0;
    }

    public static function remainingTanFor(int $orderId): float
    {
        if (! Schema::hasTable('orders')) {
            return 0.0;
        }

        return self::query()->find($orderId)?->remainingTan() ?? 0.0;
    }

    /**
     * 指定受注の受注残mを返す互換メソッド。
     *
     * @param  int  $orderId  orders.id
     * @return float 受注がない場合は0、ある場合は受注残m
     */
    public static function remainingFor(int $orderId): float
    {
        if (! Schema::hasTable('orders')) {
            return 0;
        }

        return self::remainingMetersFor($orderId);
    }

    /**
     * 一覧・詳細画面用に関連名と小数数量をまとめる。
     *
     * @return object 受注、出荷、進捗表示に必要な値
     */
    public function toDisplayObject(): object
    {
        $product = $this->product;
        $mode = $this->order_qty_mode ?? 'tan';

        $qtyTan = $mode === 'tan'
            ? QtyHelper::roundTan((float) $this->qty_tan)
            : FabricQuantity::tanFromRecord(
                ['qty_tan' => $this->qty_tan, 'qty_meters' => $this->qty_meters],
                (int) $this->product_id,
            );

        $shippedTan = FabricQuantity::tanFromRecord(
            ['qty_tan' => $this->shipped_qty_tan, 'qty' => $this->shipped_qty_m],
            (int) $this->product_id,
        );

        $qtyMeters = $mode === 'meters'
            ? (float) $this->qty_meters
            : FabricQuantity::metersFromRecord(
                ['qty_tan' => $qtyTan, 'qty_meters' => $this->qty_meters],
                (int) $this->product_id,
            );

        $shippedMeters = $this->shippedMeters();

        $shippedTanDisplay = FabricQuantity::tanFromRecord(
            ['qty_tan' => $this->shipped_qty_tan, 'qty' => $this->shipped_qty_m],
            (int) $this->product_id,
        );

        $statusInput = [
            'id' => $this->id,
            'order_qty_mode' => $mode,
            'qty_tan' => $qtyTan,
            'qty_meters' => $qtyMeters,
            'qty' => $qtyMeters,
        ];

        return (object) [
            'id' => $this->id,
            'code' => $this->code,
            'customer' => $this->customer?->name ?? '—',
            'customer_id' => $this->customer_id,
            'product_id' => $this->product_id,
            'product' => $product?->sku,
            'sku' => $product?->sku,
            'color' => $product?->color,
            'unit' => $product?->unit,
            'order_qty_mode' => $mode,
            'qty_tan' => $qtyTan,
            'shipped_tan' => $shippedTanDisplay,
            'qty_meters' => $qtyMeters,
            'shipped_meters' => $shippedMeters,
            'qty' => $qtyMeters,
            'shipped' => $shippedMeters,
            'meters_overridden' => $this->metersOverridden(),
            'order_date' => $this->order_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'planned_ship_date' => $this->planned_ship_date?->toDateString(),
            'ship_memo' => $this->ship_memo,
            'status' => DemoData::orderProgressStatus($statusInput),
            'is_new_today' => $this->order_date?->toDateString() === BusinessDate::today(),
        ];
    }
}
